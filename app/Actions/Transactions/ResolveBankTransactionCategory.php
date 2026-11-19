<?php

namespace App\Actions\Transactions;

use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\User;
use App\Support\Text\SearchText;
use Illuminate\Support\Collection;

class ResolveBankTransactionCategory
{
    private const FALLBACK_CATEGORY_NAMES = [
        'expense' => 'Other',
        'income' => 'Other Income',
    ];

    /**
     * Checked in order; the first rule with a matching keyword wins, and within it the first
     * preferred category the user has (subcategory first, then its parent). Keywords match
     * whole words of the normalised payee/description text (SI and UK merchants).
     *
     * @var array<string, list<array{keywords: list<string>, preferred: list<string>}>>
     */
    private const KEYWORD_RULES = [
        'expense' => [
            // Before "uber" (taxi) and generic "food".
            ['keywords' => ['uber eats', 'ubereats', 'wolt', 'glovo', 'deliveroo', 'just eat', 'justeat', 'foodpanda', 'takeaway'], 'preferred' => ['Takeaway & Delivery', 'Food & Drink']],
            ['keywords' => ['fuel', 'gas station', 'gasoline', 'petrol', 'diesel', 'shell', 'esso', 'chevron', 'bp', 'omv', 'mol', 'texaco', 'bencin', 'gorivo'], 'preferred' => ['Fuel', 'Car', 'Cars', 'Vehicle', 'Vehicles', 'Transportation']],
            ['keywords' => ['grocery', 'groceries', 'supermarket', 'market', 'aldi', 'lidl', 'tesco', 'spar', 'mercator', 'hofer', 'tus', 'eurospin', 'jager', 'sainsbury', 'sainsburys', 'asda', 'morrisons', 'waitrose', 'co op', 'ocado', 'iceland'], 'preferred' => ['Groceries']],
            ['keywords' => ['coffee', 'cafe', 'caffe', 'kavarna', 'starbucks', 'costa coffee', 'pret a manger', 'caffe nero', 'espresso'], 'preferred' => ['Coffee & Cafés', 'Food & Drink']],
            ['keywords' => ['bar', 'pub', 'brewery', 'pivnica', 'wetherspoon', 'wetherspoons', 'cocktail'], 'preferred' => ['Bars & Pubs', 'Food & Drink']],
            ['keywords' => ['restaurant', 'restavracija', 'gostilna', 'gostisce', 'gostišče', 'pizzeria', 'pizza', 'bistro', 'grill', 'sushi', 'burger', 'mcdonalds', 'mcdonald s', 'kfc', 'nandos', 'burger king', 'subway'], 'preferred' => ['Restaurants', 'Food & Drink']],
            ['keywords' => ['food', 'delivery'], 'preferred' => ['Food & Drink']],
            ['keywords' => ['rent a car', 'car rental', 'sixt', 'hertz', 'avis', 'europcar', 'enterprise rent'], 'preferred' => ['Travel & Vacation', 'Transportation']],
            ['keywords' => ['uber', 'lyft', 'bolt', 'taxi', 'free now', 'freenow', 'addison lee'], 'preferred' => ['Taxi & Rideshare', 'Transportation']],
            ['keywords' => ['parking', 'parkirnina', 'parkiranje', 'toll', 'dars', 'vinjeta', 'ncp', 'ringgo', 'justpark', 'paybyphone', 'dartford crossing', 'congestion charge', 'ulez'], 'preferred' => ['Parking & Tolls', 'Transportation']],
            ['keywords' => ['bus', 'train', 'tram', 'metro', 'transport', 'tfl', 'trainline', 'national rail', 'arriva', 'lpp', 'nomago', 'flixbus', 'slovenske zeleznice', 'oyster', 'avanti', 'lner', 'gwr'], 'preferred' => ['Public Transport', 'Transportation']],
            ['keywords' => ['car insurance', 'motor insurance', 'avtomobilsko zavarovanje', 'avto zavarovanje', 'kasko'], 'preferred' => ['Car Insurance', 'Transportation']],
            ['keywords' => ['car service', 'avtoservis', 'vulkanizer', 'kwik fit', 'halfords', 'mot', 'car wash', 'autopralnica'], 'preferred' => ['Car Maintenance', 'Transportation']],
            ['keywords' => ['mortgage', 'stanovanjski kredit', 'hipoteka', 'hipotekarni'], 'preferred' => ['Mortgage', 'Housing']],
            ['keywords' => ['rent', 'landlord', 'najemnina', 'najem', 'lease', 'letting', 'lettings'], 'preferred' => ['Rent', 'Housing']],
            ['keywords' => ['council tax', 'nusz', 'nadomestilo za uporabo stavbnega zemljisca'], 'preferred' => ['Council Tax', 'Housing']],
            ['keywords' => ['elektro', 'energetika', 'british gas', 'octopus energy', 'octopus', 'edf', 'e on', 'eon', 'ovo energy', 'thames water', 'severn trent', 'united utilities', 'anglian water', 'vodovod', 'komunala', 'snaga', 'gen i', 'plinarna', 'electricity', 'water', 'gas bill'], 'preferred' => ['Utilities', 'Housing']],
            ['keywords' => ['telekom', 'telekom slovenije', 'a1', 'telemach', 't 2', 'bt', 'virgin media', 'sky', 'vodafone', 'ee', 'o2', 'three uk', 'giffgaff', 'plusnet', 'talktalk', 'izi mobil', 'broadband'], 'preferred' => ['Internet & Phone', 'Housing']],
            ['keywords' => ['home insurance', 'household insurance', 'zavarovanje doma', 'zavarovanje stanovanja'], 'preferred' => ['Home Insurance', 'Housing']],
            ['keywords' => ['ikea', 'jysk', 'lesnina', 'dunelm', 'the range', 'home bargains', 'wilko', 'pohistvo', 'pohištvo'], 'preferred' => ['Furniture & Household', 'Housing']],
            ['keywords' => ['plumber', 'electrician', 'locksmith', 'handyman', 'popravilo', 'vzdrzevanje', 'vzdrževanje', 'instalater', 'inštalater'], 'preferred' => ['Repairs & Maintenance', 'Housing']],
            ['keywords' => ['vzajemna', 'dopolnilno zavarovanje', 'zdravstveno zavarovanje', 'bupa', 'vitality', 'axa health'], 'preferred' => ['Health Insurance', 'Health & Wellness']],
            ['keywords' => ['pharmacy', 'lekarna', 'lekarne', 'chemist', 'boots'], 'preferred' => ['Pharmacy', 'Health & Wellness']],
            ['keywords' => ['doctor', 'hospital', 'dentist', 'dental', 'zobozdravnik', 'ambulanta', 'clinic', 'klinika', 'medical', 'zzzs', 'nhs', 'optician', 'specsavers', 'zdravstveni dom'], 'preferred' => ['Doctor & Dentist', 'Health & Wellness']],
            ['keywords' => ['gym', 'fitness', 'puregym', 'pure gym', 'the gym group', 'david lloyd', 'nuffield', 'anytime fitness', 'yoga', 'pilates'], 'preferred' => ['Gym & Fitness', 'Health & Wellness']],
            ['keywords' => ['wellness', 'spa', 'massage', 'masaza', 'masaža'], 'preferred' => ['Health & Wellness']],
            ['keywords' => ['hairdresser', 'hair', 'frizer', 'frizerski', 'barber', 'salon', 'beauty', 'kozmetika', 'kozmeticni', 'nails', 'manikura'], 'preferred' => ['Hair & Beauty', 'Personal Care']],
            ['keywords' => ['dm', 'drogerie', 'muller', 'müller', 'superdrug', 'savers', 'rossmann'], 'preferred' => ['Toiletries', 'Personal Care']],
            // Before "amazon" (online shopping).
            ['keywords' => ['netflix', 'spotify', 'disney', 'disney plus', 'hbo', 'hbo max', 'apple tv', 'youtube premium', 'youtube', 'prime video', 'amazon prime', 'voyo', 'deezer', 'tidal', 'now tv', 'paramount', 'crunchyroll'], 'preferred' => ['Streaming', 'Subscriptions']],
            ['keywords' => ['apple com bill', 'itunes', 'app store', 'google play', 'google one', 'google storage', 'microsoft', 'adobe', 'openai', 'chatgpt', 'anthropic', 'claude ai', 'github', 'icloud', 'dropbox', 'notion', '1password', 'canva', 'jetbrains'], 'preferred' => ['Software & Apps', 'Subscriptions']],
            ['keywords' => ['membership', 'clanarina', 'članarina', 'national trust', 'costco'], 'preferred' => ['Memberships', 'Subscriptions']],
            ['keywords' => ['subscription', 'recurring', 'narocnina', 'naročnina'], 'preferred' => ['Subscriptions']],
            ['keywords' => ['ryanair', 'easyjet', 'wizz', 'wizz air', 'wizzair', 'lufthansa', 'british airways', 'klm', 'air france', 'turkish airlines', 'jet2', 'airline', 'airlines', 'flight', 'transavia', 'vueling', 'eurowings', 'austrian airlines'], 'preferred' => ['Flights', 'Travel & Vacation']],
            ['keywords' => ['booking com', 'airbnb', 'hotel', 'hostel', 'expedia', 'trivago', 'hotels com', 'premier inn', 'travelodge', 'apartmaji'], 'preferred' => ['Accommodation', 'Travel & Vacation']],
            ['keywords' => ['cinema', 'kino', 'cineplexx', 'kolosej', 'odeon', 'vue', 'cineworld', 'ticketmaster', 'eventim', 'theatre', 'theater', 'concert', 'koncert', 'festival', 'see tickets'], 'preferred' => ['Events & Cinema', 'Entertainment']],
            ['keywords' => ['steam', 'steampowered', 'playstation', 'psn', 'xbox', 'nintendo', 'epic games', 'gog com', 'twitch'], 'preferred' => ['Games', 'Entertainment']],
            ['keywords' => ['bookshop', 'waterstones', 'knjigarna', 'mladinska knjiga', 'kindle', 'audible', 'bandcamp'], 'preferred' => ['Books & Music', 'Entertainment']],
            ['keywords' => ['hobbycraft', 'hobby', 'craft'], 'preferred' => ['Hobbies', 'Entertainment']],
            // Before "amazon"/generic shopping.
            ['keywords' => ['currys', 'big bang', 'bigbang', 'apple store', 'harvey norman', 'mediamarkt', 'media markt'], 'preferred' => ['Electronics', 'Shopping']],
            ['keywords' => ['h m', 'zara', 'primark', 'uniqlo', 'deichmann', 'new yorker', 'mango', 'bershka', 'tk maxx', 'tkmaxx', 'nike', 'adidas', 'sports direct', 'jd sports', 'schuh'], 'preferred' => ['Clothing & Shoes', 'Shopping']],
            ['keywords' => ['bauhaus', 'obi', 'merkur trgovina', 'b q', 'homebase', 'wickes', 'screwfix', 'toolstation', 'garden centre', 'vrtni center', 'hornbach'], 'preferred' => ['Home & Garden', 'Shopping']],
            ['keywords' => ['amazon', 'amzn', 'ebay', 'aliexpress', 'temu', 'shein', 'zalando', 'etsy', 'mimovrste', 'vinted'], 'preferred' => ['Online Shopping', 'Shopping']],
            ['keywords' => ['vrtec', 'nursery', 'childcare', 'kindergarten', 'varstvo', 'babysitter', 'nanny'], 'preferred' => ['Childcare', 'Family & Dependents']],
            ['keywords' => ['toys', 'toy', 'smyths', 'hamleys', 'igrace', 'igrače'], 'preferred' => ['Kids', 'Family & Dependents']],
            ['keywords' => ['pet', 'pets', 'pets at home', 'fressnapf', 'veterinar', 'veterinarska', 'vet', 'vets'], 'preferred' => ['Pets', 'Family & Dependents']],
            ['keywords' => ['udemy', 'coursera', 'tuition', 'course', 'tecaj', 'tečaj', 'school', 'sola', 'šola', 'fakulteta', 'university', 'college', 'skillshare', 'duolingo'], 'preferred' => ['Courses', 'Education']],
            ['keywords' => ['textbook', 'school supplies', 'papirnica', 'stationery', 'ryman'], 'preferred' => ['Books & Supplies', 'Education']],
            ['keywords' => ['credit card', 'kreditna kartica', 'card repayment', 'amex', 'american express', 'barclaycard'], 'preferred' => ['Credit Card', 'Debt Repayment']],
            ['keywords' => ['loan', 'kredit', 'obrok kredita', 'posojilo', 'klarna', 'paypal credit', 'zopa'], 'preferred' => ['Loans', 'Debt Repayment']],
            ['keywords' => ['fee', 'fees', 'provizija', 'nadomestilo', 'bank charge', 'bank charges', 'account fee', 'vodenje racuna', 'vodenje računa', 'monthly fee', 'overdraft', 'commission', 'stroski vodenja', 'stroški vodenja'], 'preferred' => ['Bank Fees', 'Other']],
            ['keywords' => ['furs', 'davek', 'davki', 'tax', 'hmrc', 'hm revenue', 'self assessment', 'dohodnina'], 'preferred' => ['Taxes', 'Other']],
            ['keywords' => ['retail', 'shopping', 'store', 'shop'], 'preferred' => ['Shopping']],
        ],
        'income' => [
            ['keywords' => ['tax refund', 'hmrc refund', 'vracilo dohodnine', 'vračilo dohodnine', 'dohodnina', 'furs'], 'preferred' => ['Tax Refunds', 'Refunds & Reimbursements']],
            ['keywords' => ['regres', 'holiday pay', 'holiday allowance'], 'preferred' => ['Holiday Pay (Regres)', 'Wages & Salary']],
            ['keywords' => ['bonus', 'nagrada', 'bozicnica', 'božičnica', 'poslovna uspesnost', 'poslovna uspešnost'], 'preferred' => ['Bonus', 'Wages & Salary']],
            ['keywords' => ['salary', 'payroll', 'wage', 'wages', 'paycheck', 'placa', 'plača', 'osebni dohodek'], 'preferred' => ['Salary', 'Wages & Salary']],
            ['keywords' => ['reimbursement', 'expense claim', 'expenses', 'povracilo stroskov', 'povračilo stroškov', 'potni stroski', 'potni stroški', 'kilometrina', 'dnevnica'], 'preferred' => ['Expense Claims', 'Refunds & Reimbursements']],
            ['keywords' => ['refund', 'vracilo', 'vračilo', 'chargeback'], 'preferred' => ['Purchase Refunds', 'Refunds & Reimbursements', 'Tax Refunds']],
            ['keywords' => ['interest', 'obresti'], 'preferred' => ['Interest Income']],
            ['keywords' => ['dividend', 'dividende', 'dividenda'], 'preferred' => ['Investment Dividends']],
            ['keywords' => ['grant', 'scholarship', 'stipendija'], 'preferred' => ['Grants & Scholarships']],
            ['keywords' => ['pension', 'pokojnina', 'zpiz'], 'preferred' => ['Pensions & Retirement Distributions']],
            ['keywords' => ['child benefit', 'otroski dodatek', 'otroški dodatek', 'universal credit', 'dwp', 'zavod za zaposlovanje', 'center za socialno delo'], 'preferred' => ['Government Benefits & Subsidies']],
            ['keywords' => ['rent', 'najemnina', 'najem', 'tenant'], 'preferred' => ['Rental & Real Estate Income']],
            ['keywords' => ['vinted', 'ebay', 'bolha', 'depop'], 'preferred' => ['Sales (Selling Personal Items)']],
        ],
    ];

    /**
     * Money moved rather than earned or spent, in either direction; checked before the
     * income and expense rules.
     *
     * @var list<array{keywords: list<string>, preferred: list<string>}>
     */
    private const TRANSFER_RULES = [
        ['keywords' => ['exchanged to', 'exchanged from'], 'preferred' => ['Currency Exchange', 'Transfers']],
        ['keywords' => ['interactive brokers', 'pensionbee', 'al rayan', 'trading 212', 'trading212', 'vanguard'], 'preferred' => ['Investments & Savings', 'Transfers']],
    ];

    /**
     * Words in category names too common in bank text to mean anything on their own
     * ("CARD PAYMENT", "BANK TRANSFER", "ONLINE PAYMENT").
     */
    private const GENERIC_NAME_TOKENS = ['card', 'bank', 'online', 'credit', 'home', 'public', 'self', 'side', 'real', 'items', 'purchase', 'expense', 'claims', 'payment'];

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
        $signalText = $this->buildSignalText($transaction);
        $transfers = $this->categoriesFor($user, 'transfer');

        if ($transfers->isNotEmpty()) {
            foreach (self::TRANSFER_RULES as $rule) {
                if ($this->containsAnyKeyword($signalText, $rule['keywords'])) {
                    $matched = $this->matchPreferredCategory($transfers, $rule['preferred']);

                    if ($matched instanceof Category) {
                        return $matched;
                    }
                }
            }
        }

        $type = self::resolveTransactionType($transaction);
        $categories = $this->categoriesFor($user, $type);

        if ($categories->isEmpty()) {
            return null;
        }

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
        return SearchText::forTransaction($transaction);
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
                        if (mb_strlen($token) >= 4
                            && ! in_array($token, self::GENERIC_NAME_TOKENS, true)
                            && $this->containsNormalizedPhrase($signalText, $token)) {
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
        // Punctuation separates words: "TESCO,LONDON" and "(interest)" still match.
        return SearchText::normalize($value);
    }

    private function containsNormalizedPhrase(string $signalText, string $phrase): bool
    {
        if ($phrase === '') {
            return false;
        }

        return preg_match('/(^|\s)'.preg_quote($phrase, '/').'(\s|$)/u', $signalText) === 1;
    }
}
