<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\ResolveBankTransactionCategory;
use App\Actions\Wallets\CreateManualWallet;
use App\Http\Requests\StoreManualWalletRequest;
use App\Models\AssetValuation;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\InvestmentActivity;
use App\Models\InvestmentPosition;
use App\Models\Wallet;
use App\Services\Assets\AssetAccounts;
use App\Services\Assets\FixedTermSavings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Wallet::class);

        $wallets = $request->user()
            ->wallets()
            ->with(['bankConnections' => fn ($query) => $query
                ->where('status', '!=', BankConnection::STATUS_PENDING)
                ->withCount('accounts')
                ->with(['accounts' => fn ($accounts) => $accounts->where('is_active', true)])])
            ->latest()
            ->get()
            ->map(fn (Wallet $wallet): array => [
                'id' => $wallet->id,
                'name' => $wallet->name,
                'type' => $wallet->type,
                'currency' => $wallet->currency,
                'last_synced_at' => $wallet->last_synced_at?->toIso8601String(),
                'connections_count' => $wallet->bankConnections->count(),
                'accounts_count' => $wallet->bankConnections->sum('accounts_count'),
                // What the wallet holds, per currency (money owed on cards is negative).
                'balances' => $wallet->bankConnections
                    ->flatMap(fn (BankConnection $connection) => $connection->accounts)
                    ->groupBy(fn ($account): string => strtoupper((string) ($account->currency ?: $wallet->currency ?: 'EUR')))
                    ->map(fn ($accounts, string $currency): array => [
                        'currency' => $currency,
                        'amount' => round((float) $accounts->sum(fn ($account): float => (float) (($account->balances_encrypted ?? [])['current'] ?? 0)), 2),
                    ])
                    ->values(),
                'connections' => $wallet->bankConnections
                    ->map(fn (BankConnection $connection): array => $connection->toStatusArray())
                    ->values(),
            ])
            ->values();

        return Inertia::render('wallets/index', [
            'wallets' => $wallets,
        ]);
    }

    public function storeManual(
        StoreManualWalletRequest $request,
        CreateManualWallet $createManualWallet,
    ): RedirectResponse {
        Gate::authorize('create', Wallet::class);

        $account = $createManualWallet($request->user(), [
            'name' => $request->validated('name'),
            'currency' => $request->validated('currency'),
            'starting_balance' => $request->validated('starting_balance'),
        ]);

        return back()->with('success', sprintf('%s created successfully.', $account->display_name ?? $account->name ?? 'Manual wallet'));
    }

    public function show(
        Request $request,
        Wallet $wallet,
        ResolveBankTransactionCategory $resolveBankTransactionCategory,
    ): Response {
        Gate::authorize('view', $wallet);

        $wallet->load([
            'bankConnections' => fn ($query) => $query
                ->where('status', '!=', BankConnection::STATUS_PENDING)
                ->with(['accounts', 'syncRuns' => fn ($syncRunsQuery) => $syncRunsQuery->latest()->limit(5)]),
            'bankConnections.transactions' => fn ($query) => $query
                ->with('assignedCategory')
                ->latest('date')
                ->limit(50),
        ]);

        return Inertia::render('wallets/show', [
            'wallet' => [
                'id' => $wallet->id,
                'name' => $wallet->name,
                'type' => $wallet->type,
                'currency' => $wallet->currency,
                'last_synced_at' => $wallet->last_synced_at?->toIso8601String(),
                'connections' => $wallet->bankConnections->map(fn (BankConnection $connection): array => [
                    ...$connection->toStatusArray(),
                    'accounts' => $connection->accounts->map(fn (BankAccount $account): array => [
                        'id' => $account->id,
                        'name' => $account->display_name ?: $account->name,
                        'official_name' => $account->official_name,
                        'mask' => $account->maskForDisplay(),
                        'type' => $account->type,
                        'subtype' => $account->subtype,
                        'currency' => $account->currency,
                        'is_active' => $account->is_active,
                        'balances' => $account->balances_encrypted,
                        'last_synced_at' => $account->last_synced_at?->toIso8601String(),
                    ])->values(),
                    'transactions' => $connection->transactions->map(function (BankTransaction $transaction) use ($request, $resolveBankTransactionCategory): array {
                        $transactionType = ($transaction->assignedCategory
                            ?? ($resolveBankTransactionCategory)($transaction, $request->user()))?->type
                            ?? ResolveBankTransactionCategory::resolveTransactionType($transaction);

                        return [
                            'id' => $transaction->id,
                            'name' => $transaction->name,
                            'merchant_name' => $transaction->merchant_name,
                            'amount' => $transactionType === 'income'
                                ? abs((float) $transaction->amount)
                                : -abs((float) $transaction->amount),
                            'currency' => $transaction->currency,
                            'date' => $transaction->date?->toDateString(),
                            'pending' => $transaction->pending,
                        ];
                    })->values(),
                    'asset' => $connection->provider === BankConnection::PROVIDER_ASSET
                        ? $this->asset($connection)
                        : null,
                    'investments' => $connection->provider === BankConnection::PROVIDER_IBKR
                        ? $this->investments($connection)
                        : null,
                    'imports' => $connection->provider === BankConnection::PROVIDER_IMPORT
                        ? BankStatementImport::query()
                            ->whereIn('bank_account_id', $connection->accounts->pluck('id'))
                            ->latest()
                            ->limit(5)
                            ->get()
                            ->map(fn (BankStatementImport $import): array => $import->toSummaryArray())
                            ->values()
                        : [],
                    'sync_runs' => $connection->syncRuns->map(fn ($run): array => [
                        'id' => $run->id,
                        'sync_type' => $run->sync_type,
                        'status' => $run->status,
                        'added_count' => $run->added_count,
                        'modified_count' => $run->modified_count,
                        'removed_count' => $run->removed_count,
                        'started_at' => $run->started_at?->toIso8601String(),
                        'finished_at' => $run->finished_at?->toIso8601String(),
                        'error_message' => $run->error_message,
                    ])->values(),
                ])->values(),
            ],
        ]);
    }

    /**
     * Holdings and recent trades/transfers of an investment connection.
     *
     * @return array<string, mixed>
     */
    private function investments(BankConnection $connection): array
    {
        $accountIds = $connection->accounts->pluck('id');
        $positions = InvestmentPosition::query()
            ->whereIn('bank_account_id', $accountIds)
            ->orderByDesc('value_in_base')
            ->get();

        return [
            'base_currency' => $connection->accounts->first()?->currency,
            'total_value' => round((float) $connection->accounts->sum(fn (BankAccount $account): float => (float) ($account->balances_encrypted['current'] ?? 0)), 2),
            'cash' => round((float) $connection->accounts->sum(fn (BankAccount $account): float => (float) ($account->balances_encrypted['available'] ?? 0)), 2),
            'as_of' => $connection->accounts->first()?->balances_encrypted['as_of'] ?? null,
            'unrealized_pnl' => round((float) $positions->sum('unrealized_pnl_in_base'), 2),
            'holdings' => $positions->map(fn (InvestmentPosition $position): array => [
                'id' => $position->id,
                'symbol' => $position->symbol,
                'description' => $position->description,
                'asset_category' => $position->asset_category,
                'quantity' => $position->quantity,
                'mark_price' => $position->mark_price,
                'currency' => $position->currency,
                'position_value' => $position->position_value,
                'value_in_base' => $position->value_in_base,
                'unrealized_pnl' => $position->unrealized_pnl,
                'unrealized_pnl_in_base' => $position->unrealized_pnl_in_base,
            ])->values(),
            'activities' => InvestmentActivity::query()
                ->whereIn('bank_account_id', $accountIds)
                ->latest('date')
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(fn (InvestmentActivity $activity): array => [
                    'id' => $activity->id,
                    'kind' => $activity->kind,
                    'date' => $activity->date?->toDateString(),
                    'symbol' => $activity->symbol,
                    'description' => $activity->description,
                    'quantity' => $activity->quantity,
                    'price' => $activity->price,
                    'amount' => $activity->amount,
                    'commission' => $activity->commission,
                    'currency' => $activity->currency,
                ])->values(),
            'query_id' => $connection->metadata['query_id'] ?? null,
        ];
    }

    /**
     * Cash ISA terms and progress, or a pension's value history.
     *
     * @return array<string, mixed>
     */
    private function asset(BankConnection $connection): array
    {
        $account = $connection->accounts->first();
        $kind = $account?->subtype;

        if ($kind === AssetAccounts::KIND_CASH_ISA) {
            return [
                'kind' => $kind,
                'isa' => FixedTermSavings::fromTerms($connection->metadata['terms'] ?? [])?->toArray(),
                'valuations' => [],
            ];
        }

        $valuations = $account?->valuations()->orderByDesc('date')->limit(24)->get() ?? collect();

        return [
            'kind' => $kind,
            'isa' => null,
            'valuations' => $valuations->values()->map(fn (AssetValuation $valuation, int $index): array => [
                'id' => $valuation->id,
                'date' => $valuation->date?->toDateString(),
                'value' => $valuation->value,
                'change' => isset($valuations[$index + 1]) ? round($valuation->value - $valuations[$index + 1]->value, 2) : null,
            ])->all(),
        ];
    }
}
