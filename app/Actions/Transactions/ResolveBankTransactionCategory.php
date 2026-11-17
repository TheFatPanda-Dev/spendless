<?php

namespace App\Actions\Transactions;

use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ResolveBankTransactionCategory
{
    private const FALLBACK_CATEGORY_NAMES = [
        'expense' => 'Other',
        'income' => 'Other Income',
    ];

    /**
     * @var array<string, list<array{keywords: list<string>, preferred: list<string>}>>
     */
    private const KEYWORD_RULES = [
        'expense' => [
            [
                'keywords' => ['fuel', 'gas station', 'gasoline', 'petrol', 'diesel', 'shell', 'esso', 'chevron', 'bp', 'omv', 'mol', 'texaco', 'bencin', 'gorivo'],
                'preferred' => ['Fuel', 'Car', 'Cars', 'Vehicle', 'Vehicles', 'Transportation'],
            ],
            [
                'keywords' => ['grocery', 'groceries', 'supermarket', 'market', 'aldi', 'lidl', 'tesco', 'spar', 'mercator', 'hofer', 'tus', 'eurospin', 'jager', 'sainsbury', 'sainsburys', 'asda', 'morrisons', 'waitrose', 'co op', 'ocado'],
                'preferred' => ['Groceries'],
            ],
            [
                'keywords' => ['restaurant', 'cafe', 'coffee', 'bar', 'pub', 'food', 'takeaway', 'delivery', 'mcdonalds', 'wolt', 'glovo', 'deliveroo', 'just eat', 'restavracija', 'gostilna', 'kavarna', 'pizzeria'],
                'preferred' => ['Food & Drink'],
            ],
            [
                'keywords' => ['uber', 'lyft', 'bolt', 'taxi', 'bus', 'train', 'tram', 'metro', 'toll', 'parking', 'transport', 'tfl', 'trainline', 'national rail', 'dars', 'vinjeta', 'parkirnina', 'arriva', 'lpp'],
                'preferred' => ['Transportation', 'Car', 'Cars', 'Vehicle', 'Vehicles', 'Travel & Vacation'],
            ],
            [
                'keywords' => ['rent', 'mortgage', 'landlord', 'apartment', 'lease', 'najemnina', 'council tax', 'elektro', 'energetika', 'british gas', 'octopus energy'],
                'preferred' => ['Housing'],
            ],
            [
                'keywords' => ['pharmacy', 'doctor', 'hospital', 'dentist', 'wellness', 'medical', 'lekarna', 'boots', 'zzzs'],
                'preferred' => ['Health & Wellness'],
            ],
            [
                'keywords' => ['netflix', 'spotify', 'subscription', 'membership', 'recurring'],
                'preferred' => ['Subscriptions'],
            ],
            [
                'keywords' => ['amazon', 'retail', 'shopping', 'store'],
                'preferred' => ['Shopping'],
            ],
        ],
        'income' => [
            [
                'keywords' => ['salary', 'payroll', 'wage', 'paycheck', 'placa', 'plača', 'regres'],
                'preferred' => ['Wages & Salary'],
            ],
            [
                'keywords' => ['refund', 'reimbursement'],
                'preferred' => ['Refunds & Reimbursements', 'Tax Refunds'],
            ],
            [
                'keywords' => ['interest', 'obresti'],
                'preferred' => ['Interest Income'],
            ],
            [
                'keywords' => ['dividend'],
                'preferred' => ['Investment Dividends'],
            ],
            [
                'keywords' => ['grant', 'scholarship'],
                'preferred' => ['Grants & Scholarships'],
            ],
        ],
    ];

    /**
     * @var array<string, Collection<int, Category>>
     */
    private array $categoriesByUserAndType = [];

    /**
     * @var array<int, int>
     */
    private array $depthByCategoryId = [];

    public function __invoke(BankTransaction $transaction, User $user): ?Category
    {
        $type = self::resolveTransactionType($transaction);
        $categories = $this->categoriesFor($user, $type);

        if ($categories->isEmpty()) {
            return null;
        }

        $signalText = $this->buildSignalText($transaction);

        foreach (self::KEYWORD_RULES[$type] ?? [] as $rule) {
            if (! $this->containsAnyKeyword($signalText, $rule['keywords'])) {
                continue;
            }

            $matchedCategory = $this->matchPreferredCategory($categories, $rule['preferred']);

            if ($matchedCategory instanceof Category) {
                return $matchedCategory;
            }
        }

        $directMatch = $this->matchCategoryBySignal($categories, $signalText);

        if ($directMatch instanceof Category) {
            return $directMatch;
        }

        return $categories->first(
            fn (Category $category): bool => $category->name === self::FALLBACK_CATEGORY_NAMES[$type],
        );
    }

    /**
     * Amounts are signed: money in is positive, money out is negative.
     */
    public static function resolveTransactionType(BankTransaction $transaction): string
    {
        return (float) $transaction->amount < 0 ? 'expense' : 'income';
    }

    private function categoriesFor(User $user, string $type): Collection
    {
        $cacheKey = $user->id.':'.$type;

        if (! array_key_exists($cacheKey, $this->categoriesByUserAndType)) {
            $this->categoriesByUserAndType[$cacheKey] = Category::query()
                ->where('user_id', $user->id)
                ->where('type', $type)
                ->orderBy('name')
                ->get();
        }

        return $this->categoriesByUserAndType[$cacheKey];
    }

    private function buildSignalText(BankTransaction $transaction): string
    {
        return collect([
            $transaction->merchant_name,
            $transaction->payee,
            $transaction->name,
            $transaction->description,
        ])
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => $this->normalizeText($value))
            ->implode(' ');
    }

    /**
     * @param  list<string>  $keywords
     */
    private function containsAnyKeyword(string $signalText, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if ($this->containsNormalizedPhrase($signalText, $this->normalizeText($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @param  list<string>  $preferredNames
     */
    private function matchPreferredCategory(Collection $categories, array $preferredNames): ?Category
    {
        foreach ($preferredNames as $preferredName) {
            $normalizedName = $this->normalizeText($preferredName);

            $matchingCategory = $categories
                ->filter(fn (Category $category): bool => $this->normalizeText((string) $category->name) === $normalizedName)
                ->sortByDesc(fn (Category $category): int => $this->categoryDepth($category, $categories))
                ->first();

            if ($matchingCategory instanceof Category) {
                return $matchingCategory;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    private function matchCategoryBySignal(Collection $categories, string $signalText): ?Category
    {
        $scoredCategories = $categories
            ->map(function (Category $category) use ($signalText, $categories): array {
                $score = 0;
                $normalizedName = $this->normalizeText((string) $category->name);

                if ($normalizedName !== '' && ! in_array($normalizedName, ['other', 'other income'], true)) {
                    if ($this->containsNormalizedPhrase($signalText, $normalizedName)) {
                        $score += 50;
                    }

                    foreach (explode(' ', $normalizedName) as $token) {
                        if (mb_strlen($token) >= 4 && $this->containsNormalizedPhrase($signalText, $token)) {
                            $score += 8;
                        }
                    }
                }

                return [
                    'category' => $category,
                    'score' => $score,
                    'depth' => $this->categoryDepth($category, $categories),
                ];
            })
            ->filter(fn (array $item): bool => $item['score'] > 0)
            ->sortByDesc(fn (array $item): array => [$item['score'], $item['depth']])
            ->values();

        $bestMatch = $scoredCategories->first();

        return is_array($bestMatch) ? $bestMatch['category'] : null;
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    private function categoryDepth(Category $category, Collection $categories): int
    {
        if (array_key_exists($category->id, $this->depthByCategoryId)) {
            return $this->depthByCategoryId[$category->id];
        }

        $depth = 0;
        $categoriesById = $categories->keyBy('id');
        $currentParentId = $category->parent_id;

        while ($currentParentId !== null) {
            $depth++;
            $currentParent = $categoriesById->get($currentParentId);
            $currentParentId = $currentParent instanceof Category
                ? $currentParent->parent_id
                : null;
        }

        return $this->depthByCategoryId[$category->id] = $depth;
    }

    private function normalizeText(string $value): string
    {
        return (string) Str::of($value)
            ->replace(['_', '-', '/', '&'], ' ')
            ->lower()
            ->squish();
    }

    private function containsNormalizedPhrase(string $signalText, string $phrase): bool
    {
        if ($phrase === '') {
            return false;
        }

        return preg_match('/(^|\s)'.preg_quote($phrase, '/').'(\s|$)/u', $signalText) === 1;
    }
}
