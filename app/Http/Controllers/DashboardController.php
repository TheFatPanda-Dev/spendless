<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\ResolveBankTransactionCategory;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Services\Currency\FreeCurrencyApi;
use App\Support\Localization\NumberLocaleOptions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private FreeCurrencyApi $freeCurrencyApi,
        private ResolveBankTransactionCategory $resolveBankTransactionCategory,
    ) {}

    public function __invoke(Request $request): Response
    {
        $startDate = Carbon::parse((string) $request->query('start_date', now()->startOfMonth()->toDateString()))->startOfDay();
        $endDate = Carbon::parse((string) $request->query('end_date', now()->endOfMonth()->toDateString()))->endOfDay();

        $accounts = BankAccount::query()
            ->where('is_active', true)
            ->whereHas('connection', fn ($query) => $query
                ->where('user_id', $request->user()->id))
            ->with('connection')
            ->orderByRaw("COALESCE(display_name, name, official_name, '') asc")
            ->get()
            ->map(function (BankAccount $account): array {
                $balances = is_array($account->balances_encrypted) ? $account->balances_encrypted : [];
                $currency = (string) ($account->currency_code ?: $account->currency ?: 'EUR');

                return [
                    'id' => $account->id,
                    'display_name' => $account->display_name,
                    'name' => $account->name,
                    'official_name' => $account->official_name,
                    'institution_name' => $account->connection?->institution_name,
                    'mask' => $account->mask_encrypted ? str_pad(substr((string) $account->mask_encrypted, -4), 4, '*', STR_PAD_LEFT) : null,
                    'type' => $account->type,
                    'subtype' => $account->subtype,
                    'currency' => $currency,
                    'current_balance' => (float) ($balances['current'] ?? 0),
                    'available_balance' => isset($balances['available']) ? (float) $balances['available'] : null,
                    'last_synced_at' => $account->last_synced_at?->toIso8601String(),
                ];
            })
            ->values();

        $baseTransactionQuery = BankTransaction::query()
            ->whereNull('removed_at')
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereHas('connection', fn ($query) => $query
                ->where('user_id', $request->user()->id));

        $periodTransactions = (clone $baseTransactionQuery)
            ->with('assignedCategory')
            ->select([
                'id',
                'category_id',
                'amount',
                'currency',
                'iso_currency_code',
                'unofficial_currency_code',
                'merchant_name',
                'payee',
                'name',
                'description',
                'category',
                'personal_finance_category',
            ])
            ->get();

        $periodExpensesByCurrency = [];
        $periodIncomeByCurrency = [];
        $expenses = 0.0;
        $income = 0.0;
        $user = $request->user();

        foreach ($periodTransactions as $transaction) {
            $currency = strtoupper((string) (
                $transaction->iso_currency_code
                ?: $transaction->currency
                ?: $transaction->unofficial_currency_code
                ?: 'EUR'
            ));

            $resolvedCategory = $transaction->assignedCategory
                ?? ($this->resolveBankTransactionCategory)($transaction, $user);
            $transactionType = $resolvedCategory?->type
                ?? ResolveBankTransactionCategory::resolveTransactionType($transaction);
            $amount = abs((float) $transaction->amount);

            if ($transactionType === 'expense') {
                $periodExpensesByCurrency[$currency] = ($periodExpensesByCurrency[$currency] ?? 0) - $amount;
                $expenses += $amount;

                continue;
            }

            $periodIncomeByCurrency[$currency] = ($periodIncomeByCurrency[$currency] ?? 0) + $amount;
            $income += $amount;
        }

        $totalBalance = (float) $accounts->sum('current_balance');
        $periodChange = $income - $expenses;
        $baseCurrency = strtoupper((string) ($request->user()->preferred_base_currency ?: 'EUR'));
        $numberLocale = NumberLocaleOptions::normalize((string) ($request->user()->preferred_number_locale ?: 'en-GB'));
        $exchangeRates = $this->freeCurrencyApi->eurPerUnit(
            $accounts
                ->pluck('currency')
                ->filter()
                ->merge(array_keys($periodExpensesByCurrency))
                ->merge(array_keys($periodIncomeByCurrency))
                ->push($baseCurrency)
                ->unique()
                ->values()
                ->all(),
        );

        $periodChangeByCurrency = collect($periodIncomeByCurrency)
            ->keys()
            ->merge(array_keys($periodExpensesByCurrency))
            ->unique()
            ->mapWithKeys(fn (string $currency): array => [
                $currency => (float) (($periodIncomeByCurrency[$currency] ?? 0) + ($periodExpensesByCurrency[$currency] ?? 0)),
            ])
            ->all();

        return Inertia::render('dashboard', [
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_balance' => $totalBalance,
                'period_change' => $periodChange,
                'period_expenses' => -$expenses,
                'period_income' => $income,
            ],
            'accounts' => $accounts,
            'base_currency' => $baseCurrency,
            'number_locale' => $numberLocale,
            'exchange_rates' => [
                'eur_per_unit' => $exchangeRates,
            ],
            'period_breakdown' => [
                'change_by_currency' => $periodChangeByCurrency,
                'expenses_by_currency' => $periodExpensesByCurrency,
                'income_by_currency' => $periodIncomeByCurrency,
            ],
        ]);
    }
}
