<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\ResolveBankTransactionCategory;
use App\Actions\Wallets\CreateManualWallet;
use App\Http\Requests\StoreManualWalletRequest;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\Wallet;
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
                ->withCount('accounts')])
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
}
