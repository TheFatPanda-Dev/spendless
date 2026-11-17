<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\ResolveBankTransactionCategory;
use App\Models\BankAccount;
use App\Models\BankConnection;
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

        $connectionIds = $request->user()
            ->bankConnections()
            ->where('status', '!=', BankConnection::STATUS_PENDING)
            ->pluck('id');

        $accounts = BankAccount::query()
            ->where('is_active', true)
            ->whereIn('bank_connection_id', $connectionIds)
            ->with('connection:id,institution_name,institution_logo,provider,status')
            ->orderByRaw("COALESCE(display_name, name, official_name, '') asc")
            ->get()
            ->map(function (BankAccount $account): array {
                $balances = is_array($account->balances_encrypted) ? $account->balances_encrypted : [];
                $currency = (string) ($account->currency ?: 'EUR');

                return [
                    'id' => $account->id,
                    'display_name' => $account->display_name,
                    'name' => $account->name,
                    'official_name' => $account->official_name,
                    'institution_name' => $account->connection?->institution_name,
                    'institution_logo' => $account->connection?->institution_logo,
                    'connection_status' => $account->connection?->status,
                    'mask' => $account->maskForDisplay(),
                    'type' => $account->type,
                    'subtype' => $account->subtype,
                    'currency' => $currency,
                    'current_balance' => (float) ($balances['current'] ?? 0),
                    'available_balance' => isset($balances['available']) ? (float) $balances['available'] : null,
                    'last_synced_at' => $account->last_synced_at?->toIso8601String(),
                ];
            })
            ->values();

        $periodTransactions = BankTransaction::query()
            ->whereIn('bank_connection_id', $connectionIds)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with('assignedCategory')
            ->select([
                'id',
                'category_id',
                'amount',
                'currency',
                'merchant_name',
                'payee',
                'name',
                'description',
            ])
            ->get();

        $periodExpensesByCurrency = [];
        $periodIncomeByCurrency = [];
        $expenses = 0.0;
        $income = 0.0;
        $user = $request->user();

        foreach ($periodTransactions as $transaction) {
            $currency = strtoupper((string) ($transaction->currency ?: 'EUR'));

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
