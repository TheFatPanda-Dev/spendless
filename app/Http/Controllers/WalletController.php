<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWalletRequest;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function addAccount(Request $request): Response
    {
        Gate::authorize('create', Wallet::class);

        return Inertia::render('wallets/add-account');
    }

    public function quickConnect(Request $request): RedirectResponse
    {
        Gate::authorize('create', Wallet::class);

        $wallet = $request->user()->wallets()->create([
            'name' => 'Bank Wallet '.now()->format('M d H:i'),
            'type' => 'bank',
            'currency' => 'EUR',
        ]);

        return to_route('wallets.show', [
            'wallet' => $wallet,
            'auto_connect' => 1,
        ])->with('success', 'Wallet created. Connect your bank account via Plaid.');
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Wallet::class);

        $wallets = $request->user()
            ->wallets()
            ->with([
                'bankConnections' => fn ($query) => $query
                    ->where('provider', 'plaid')
                    ->withCount('accounts'),
                'bankConnections.accounts',
            ])
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
                'connections' => $wallet->bankConnections->map(fn ($connection): array => [
                    'id' => $connection->id,
                    'status' => $connection->status,
                    'institution_name' => $connection->institution_name,
                    'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
                    'error_message' => $connection->error_message,
                ])->values(),
            ])
            ->values();

        return Inertia::render('wallets/index', [
            'wallets' => $wallets,
        ]);
    }

    public function store(StoreWalletRequest $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', Wallet::class);

        $wallet = $request->user()->wallets()->create($request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $wallet->id,
            ])->setStatusCode(201);
        }

        return to_route('wallets.show', $wallet)->with('success', 'Wallet created successfully.');
    }

    public function show(Request $request, Wallet $wallet): Response
    {
        Gate::authorize('view', $wallet);

        $wallet->load([
            'bankConnections' => fn ($query) => $query
                ->where('provider', 'plaid')
                ->with(['accounts', 'syncRuns' => fn ($syncRunsQuery) => $syncRunsQuery->latest()->limit(5)]),
            'bankConnections.transactions' => fn ($query) => $query
                ->whereNull('removed_at')
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
                'connections' => $wallet->bankConnections->map(fn ($connection): array => [
                    'id' => $connection->id,
                    'status' => $connection->status,
                    'institution_name' => $connection->institution_name,
                    'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
                    'last_webhook_at' => $connection->last_webhook_at?->toIso8601String(),
                    'error_message' => $connection->error_message,
                    'accounts' => $connection->accounts->map(fn ($account): array => [
                        'id' => $account->id,
                        'name' => $account->name,
                        'official_name' => $account->official_name,
                        'mask' => $account->mask_encrypted ? str_pad(substr((string) $account->mask_encrypted, -4), 4, '*', STR_PAD_LEFT) : null,
                        'type' => $account->type,
                        'subtype' => $account->subtype,
                        'currency' => $account->currency_code,
                        'balances' => $account->balances_encrypted,
                        'last_synced_at' => $account->last_synced_at?->toIso8601String(),
                    ])->values(),
                    'transactions' => $connection->transactions->map(fn ($transaction): array => [
                        'id' => $transaction->id,
                        'name' => $transaction->name,
                        'merchant_name' => $transaction->merchant_name,
                        'amount' => (float) $transaction->amount,
                        'currency' => $transaction->iso_currency_code,
                        'date' => $transaction->date?->toDateString(),
                        'pending' => $transaction->pending,
                    ])->values(),
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
