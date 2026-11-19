<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\ResolveBankTransactionCategory;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Services\Reports\MonthlySpending;
use App\Support\Transactions\TransactionPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Overview: what's left to spend this month, where it went, what happened lately and
 * where the money sits (in each wallet's own currency).
 */
class DashboardController extends Controller
{
    private const CATEGORY_ROWS = 4;

    private const RECENT_ROWS = 5;

    public function __invoke(Request $request, MonthlySpending $report, ResolveBankTransactionCategory $resolveCategory): Response
    {
        $user = $request->user();
        [$from, $to] = self::month($request);
        $summary = $report->summarize($user, $from, $to);
        $budgets = $report->budgets($user, $summary);
        $base = $summary['base_currency'];
        $presenter = new TransactionPresenter($user, $resolveCategory);

        $accounts = BankAccount::query()
            ->where('is_active', true)
            ->whereIn('bank_connection_id', $user->bankConnections()
                ->where('status', '!=', BankConnection::STATUS_PENDING)
                ->select('id'))
            ->with('connection:id,wallet_id,institution_name,institution_logo,provider,status,last_synced_at,consent_valid_until')
            ->orderByRaw("COALESCE(display_name, name, official_name, '') asc")
            ->get();

        $wallets = $accounts->map(function (BankAccount $account): array {
            $balances = is_array($account->balances_encrypted) ? $account->balances_encrypted : [];

            return [
                'id' => $account->id,
                'wallet_id' => $account->connection?->wallet_id,
                'name' => (string) ($account->display_name ?: $account->name ?: 'Account'),
                'institution' => $account->connection?->institution_name,
                'logo' => $account->connection?->logoUrl(),
                'provider' => $account->connection?->provider,
                'status' => $account->connection?->status,
                'mask' => $account->maskForDisplay(),
                'currency' => strtoupper((string) ($account->currency ?: 'EUR')),
                'balance' => round((float) ($balances['current'] ?? 0), 2),
                'last_synced_at' => ($account->last_synced_at ?? $account->connection?->last_synced_at)?->toIso8601String(),
            ];
        })->values();

        $netWorth = $wallets->groupBy('currency')
            ->map(fn ($items, string $currency): array => ['currency' => $currency, 'amount' => round((float) $items->sum('balance'), 2)])
            ->sortByDesc(fn (array $row): float => abs($report->toBase($row['amount'], $row['currency'], $base)))
            ->values();

        $recent = BankTransaction::query()
            ->whereIn('bank_account_id', $accounts->modelKeys())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('external_uid', 'not like', 'manual-opening-balance-%')
            ->with('tags:id')
            ->latest('date')
            ->latest('id')
            ->limit(self::RECENT_ROWS)
            ->get()
            ->map(fn (BankTransaction $transaction): array => $presenter->present($transaction, $accounts->firstWhere('id', $transaction->bank_account_id)))
            ->values();

        $categories = collect($summary['by_category']);
        $shown = $categories->take(self::CATEGORY_ROWS)->values();
        $rest = $categories->slice(self::CATEGORY_ROWS);

        if ($rest->isNotEmpty()) {
            $shown->push(['id' => null, 'name' => 'Other', 'icon' => 'package', 'color' => null, 'amount' => round((float) $rest->sum('amount'), 2), 'other' => true]);
        }

        $overBudget = collect($budgets['categories'])
            ->filter(fn (array $line): bool => $line['left'] < 0)
            ->map(fn (array $line): array => ['name' => $line['name'], 'category_id' => $line['category_id'], 'over' => round(-$line['left'], 2)])
            ->values();

        return Inertia::render('dashboard', [
            'month' => ['start' => $from->toDateString(), 'end' => $to->toDateString()],
            'baseCurrency' => $base,
            'budget' => $budgets['total'],
            'spent' => $summary['spent'],
            'income' => $summary['income'],
            'categories' => $shown,
            'overBudget' => $overBudget,
            'recent' => $recent,
            'wallets' => $wallets,
            'netWorth' => [
                'by_currency' => $netWorth,
                'total' => round($netWorth->sum(fn (array $row): float => $report->toBase($row['amount'], $row['currency'], $base)), 2),
            ],
            'addTransaction' => [
                'accounts' => $presenter->manualAccounts(),
                'categories' => $presenter->categoryOptions(),
            ],
        ]);
    }

    /**
     * The month the page shows: start_date/end_date from the query, else this month.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public static function month(Request $request): array
    {
        try {
            $from = CarbonImmutable::parse((string) $request->query('start_date', now()->startOfMonth()->toDateString()))->startOfDay();
            $to = CarbonImmutable::parse((string) $request->query('end_date', $from->endOfMonth()->toDateString()))->endOfDay();
        } catch (\Throwable) {
            $from = CarbonImmutable::now()->startOfMonth();
            $to = $from->endOfMonth();
        }

        return $to->lessThan($from) ? [$from, $from->endOfMonth()] : [$from, $to];
    }
}
