<?php

namespace App\Services\Reports;

use App\Actions\Transactions\ResolveBankTransactionCategory;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use App\Services\Currency\FreeCurrencyApi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * One month of money for a user, in their base currency: what went out per main
 * category, what came in, and how that compares with their budgets. Amounts in other
 * currencies are converted at the day's rate; the original amounts stay on the
 * transactions themselves.
 */
class MonthlySpending
{
    /** @var array<string, float>|null */
    private ?array $rates = null;

    public function __construct(
        private readonly FreeCurrencyApi $currencyApi,
        private readonly ResolveBankTransactionCategory $resolveCategory,
    ) {}

    public static function baseCurrency(User $user): string
    {
        return strtoupper((string) ($user->preferred_base_currency ?: 'EUR'));
    }

    /**
     * @return list<int>
     */
    public static function accountIds(User $user): array
    {
        return BankAccount::query()
            ->whereIn('bank_connection_id', $user->bankConnections()
                ->where('status', '!=', BankConnection::STATUS_PENDING)
                ->select('id'))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return array{
     *     base_currency: string,
     *     spent: float,
     *     income: float,
     *     transactions: int,
     *     by_category: list<array{id: int|null, name: string, icon: string, amount: float}>,
     * }
     */
    public function summarize(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $base = self::baseCurrency($user);
        $categories = $user->categories()->get(['id', 'parent_id', 'name', 'type', 'icon', 'color'])->keyBy('id');
        $spentByCategory = [];
        $spent = 0.0;
        $income = 0.0;
        $count = 0;

        $transactions = BankTransaction::query()
            ->whereIn('bank_account_id', self::accountIds($user))
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            // Opening balances set up a wallet; they are not income.
            ->where('external_uid', 'not like', 'manual-opening-balance-%')
            ->get(['id', 'category_id', 'amount', 'currency', 'merchant_name', 'payee', 'name', 'description']);

        $this->loadRates($transactions->pluck('currency')->push($base)->all());

        foreach ($transactions as $transaction) {
            $amount = $this->toBase((float) $transaction->amount, (string) ($transaction->currency ?: $base), $base);
            $count++;

            if ($amount >= 0) {
                $income += $amount;

                continue;
            }

            $category = $transaction->category_id !== null
                ? $categories->get($transaction->category_id)
                : ($this->resolveCategory)($transaction, $user);
            $main = $category instanceof Category ? $this->mainCategory($category, $categories) : null;
            $key = $main?->id ?? 0;

            $spentByCategory[$key] ??= [
                'id' => $main?->id,
                'name' => $main?->name ?? 'Uncategorised',
                'icon' => $main?->icon ?? 'package',
                'amount' => 0.0,
            ];
            $spentByCategory[$key]['amount'] += -$amount;
            $spent += -$amount;
        }

        $byCategory = collect($spentByCategory)
            ->map(fn (array $row): array => [...$row, 'amount' => round($row['amount'], 2)])
            ->sortByDesc('amount')
            ->values()
            ->all();

        return [
            'base_currency' => $base,
            'spent' => round($spent, 2),
            'income' => round($income, 2),
            'transactions' => $count,
            'by_category' => $byCategory,
        ];
    }

    /**
     * Budgets for the month: the total and every category budget, with what was spent.
     *
     * @param  array{spent: float, by_category: list<array{id: int|null, name: string, icon: string, amount: float}>}  $summary
     * @return array{
     *     total: array{id: int, amount: float, spent: float, left: float, percent: int}|null,
     *     categories: list<array{id: int, category_id: int, name: string, icon: string, amount: float, spent: float, left: float, percent: int}>,
     * }
     */
    public function budgets(User $user, array $summary): array
    {
        $budgets = Budget::query()->where('user_id', $user->id)->with('category:id,name,icon')->get();
        $spentByCategory = collect($summary['by_category'])->keyBy('id');
        $total = $budgets->firstWhere('category_id', null);

        return [
            'total' => $total instanceof Budget ? $this->budgetLine($total, $summary['spent']) : null,
            'categories' => $budgets
                ->whereNotNull('category_id')
                ->filter(fn (Budget $budget): bool => $budget->category !== null)
                ->map(fn (Budget $budget): array => [
                    ...$this->budgetLine($budget, (float) ($spentByCategory->get($budget->category_id)['amount'] ?? 0)),
                    'category_id' => (int) $budget->category_id,
                    'name' => (string) $budget->category->name,
                    'icon' => (string) $budget->category->icon,
                ])
                ->sortByDesc(fn (array $line): float => $line['spent'] / max($line['amount'], 0.01))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{id: int, amount: float, spent: float, left: float, percent: int}
     */
    private function budgetLine(Budget $budget, float $spent): array
    {
        $amount = (float) $budget->amount;

        return [
            'id' => $budget->id,
            'amount' => round($amount, 2),
            'spent' => round($spent, 2),
            'left' => round($amount - $spent, 2),
            'percent' => $amount > 0 ? (int) round($spent / $amount * 100) : 0,
        ];
    }

    public function toBase(float $amount, string $currency, string $base): float
    {
        if ($currency === $base) {
            return $amount;
        }

        $rates = $this->rates ?? [];
        $from = $rates[$currency] ?? null;
        $to = $rates[$base] ?? null;

        if ($from === null || $to === null || $to <= 0) {
            $this->loadRates([$currency, $base]);
            $from = $this->rates[$currency] ?? 1.0;
            $to = $this->rates[$base] ?? 1.0;
        }

        return $amount * $from / $to;
    }

    /**
     * @param  list<string>  $currencies
     */
    private function loadRates(array $currencies): void
    {
        $wanted = collect($currencies)->map(fn ($currency): string => strtoupper((string) $currency))->filter()->unique();
        $missing = $wanted->reject(fn (string $currency): bool => isset($this->rates[$currency]));

        if ($missing->isNotEmpty()) {
            $this->rates = [...($this->rates ?? []), ...$this->currencyApi->eurPerUnit($missing->values()->all())];
        }
    }

    /**
     * @param  Collection<int|string, Category>  $categories
     */
    private function mainCategory(Category $category, Collection $categories): Category
    {
        $current = $category;
        $guard = 0;

        while ($current->parent_id !== null && $guard++ < 8) {
            $parent = $categories->get($current->parent_id);

            if (! $parent instanceof Category) {
                break;
            }

            $current = $parent;
        }

        return $current;
    }
}
