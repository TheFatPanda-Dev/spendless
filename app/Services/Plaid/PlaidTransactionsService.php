<?php

namespace App\Services\Plaid;

use App\Actions\Transactions\ResolveBankTransactionCategory;
use App\Jobs\SyncPlaidConnectionJob;
use App\Models\BankAccount;
use App\Models\BankActivityLog;
use App\Models\BankConnection;
use App\Models\BankSyncRun;
use App\Models\BankTransaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlaidTransactionsService
{
    public function __construct(
        private readonly PlaidClient $plaidClient,
        private readonly PlaidWebhookService $webhookService,
        private readonly ResolveBankTransactionCategory $resolveBankTransactionCategory,
    ) {}

    public function createLinkToken(User $user, Wallet $wallet, ?string $redirectUri = null): string
    {
        $countryCodes = config('services.plaid.country_codes', ['US']);

        $payload = [
            'user' => [
                'client_user_id' => (string) $user->id,
            ],
            'client_name' => (string) config('app.name', 'SpendLess'),
            'products' => ['transactions'],
            'country_codes' => $countryCodes,
            'language' => (string) config('services.plaid.language', 'en'),
            'webhook' => (string) config('services.plaid.webhook_url'),
            'transactions' => [
                'days_requested' => (int) config('services.plaid.initial_days_requested', 730),
            ],
            'account_filters' => [
                'depository' => [
                    'account_subtypes' => ['checking', 'savings', 'cash management'],
                ],
            ],
        ];

        if ($redirectUri !== null && $redirectUri !== '') {
            $payload['redirect_uri'] = $redirectUri;
        }

        $response = $this->plaidClient->createLinkToken($payload);

        return (string) Arr::get($response, 'link_token');
    }

    public function exchangePublicToken(string $publicToken, Wallet $wallet, User $user): BankConnection
    {
        $exchange = $this->plaidClient->exchangePublicToken($publicToken);
        $accessToken = (string) Arr::get($exchange, 'access_token');
        $itemId = (string) Arr::get($exchange, 'item_id');

        $item = $this->plaidClient->getItem($accessToken);
        $institutionId = (string) Arr::get($item, 'item.institution_id', '');
        $institutionName = null;

        if ($institutionId !== '') {
            $institution = $this->plaidClient->getInstitution(
                $institutionId,
                config('services.plaid.country_codes', ['US']),
            );

            $institutionName = (string) Arr::get($institution, 'institution.name');
        }

        $connection = DB::transaction(function () use ($wallet, $user, $accessToken, $itemId, $institutionId, $institutionName, $item): BankConnection {
            $connection = BankConnection::updateOrCreate(
                [
                    'provider' => 'plaid',
                    'plaid_item_id_hash' => hash('sha256', $itemId),
                ],
                [
                    'wallet_id' => $wallet->id,
                    'user_id' => $user->id,
                    'aspsp_name' => $institutionName !== '' ? $institutionName : 'Plaid Institution',
                    'aspsp_country' => strtoupper((string) Arr::first(config('services.plaid.country_codes', ['US']), fn (string $code): bool => $code !== '')),
                    'state' => (string) Str::uuid(),
                    'status' => 'connected',
                    'plaid_item_id_encrypted' => $itemId,
                    'plaid_access_token_encrypted' => $accessToken,
                    'institution_id' => $institutionId !== '' ? $institutionId : null,
                    'institution_name' => $institutionName !== '' ? $institutionName : 'Linked institution',
                    'available_products' => Arr::get($item, 'item.available_products', []),
                    'metadata' => [
                        'item' => Arr::get($item, 'item', []),
                    ],
                    'authorized_at' => now(),
                    'error_code' => null,
                    'error_message' => null,
                    'last_sync_error' => null,
                ],
            );

            $this->syncAccounts($connection);

            return $connection;
        });

        SyncPlaidConnectionJob::dispatch($connection->id, 'initial', false);

        return $connection;
    }

    public function syncItem(BankConnection $connection, string $reason = 'manual'): BankSyncRun
    {
        $connection->refresh();

        $lock = Cache::lock('plaid-sync-connection-'.$connection->id, 180);

        if (! $lock->get()) {
            $run = $connection->syncRuns()->create([
                'sync_type' => $reason,
                'status' => 'skipped',
                'started_at' => now(),
                'finished_at' => now(),
                'error_message' => 'Sync already in progress.',
            ]);

            return $run;
        }

        try {
            $cursorBefore = $connection->plaid_cursor;

            $run = $connection->syncRuns()->create([
                'sync_type' => $reason,
                'status' => 'running',
                'cursor_before' => $cursorBefore,
                'started_at' => now(),
            ]);

            $accountsByPlaidId = $this->syncAccounts($connection);

            $cursor = $cursorBefore;
            $addedCount = 0;
            $modifiedCount = 0;
            $removedCount = 0;
            $hasMore = true;

            while ($hasMore) {
                $response = $this->plaidClient->syncTransactions(
                    (string) $connection->plaid_access_token_encrypted,
                    $cursor,
                    500,
                );

                $cursor = (string) Arr::get($response, 'next_cursor', $cursor);
                $hasMore = (bool) Arr::get($response, 'has_more', false);

                $added = collect(Arr::get($response, 'added', []));
                $modified = collect(Arr::get($response, 'modified', []));
                $removed = collect(Arr::get($response, 'removed', []));

                $addedCount += $added->count();
                $modifiedCount += $modified->count();
                $removedCount += $removed->count();

                $added->each(fn (array $transaction): int => $this->upsertTransaction($connection, $transaction, $accountsByPlaidId));
                $modified->each(fn (array $transaction): int => $this->upsertTransaction($connection, $transaction, $accountsByPlaidId));

                $removedTransactionIds = $removed
                    ->map(fn (array $transaction): string => (string) Arr::get($transaction, 'transaction_id'))
                    ->filter(fn (string $transactionId): bool => $transactionId !== '')
                    ->values()
                    ->all();

                if ($removedTransactionIds !== []) {
                    BankTransaction::query()
                        ->where('bank_connection_id', $connection->id)
                        ->whereIn('plaid_transaction_id', $removedTransactionIds)
                        ->update(['removed_at' => now()]);
                }
            }

            $connection->update([
                'plaid_cursor' => $cursor,
                'last_synced_at' => now(),
                'next_sync_at' => now()->addHours(4),
                'status' => 'connected',
                'sync_failures' => 0,
                'error_code' => null,
                'error_message' => null,
                'last_sync_error' => null,
            ]);

            $connection->wallet?->update(['last_synced_at' => now()]);

            $run->update([
                'status' => 'succeeded',
                'cursor_after' => $cursor,
                'added_count' => $addedCount,
                'modified_count' => $modifiedCount,
                'removed_count' => $removedCount,
                'finished_at' => now(),
            ]);

            BankActivityLog::query()->create([
                'user_id' => $connection->user_id,
                'event_type' => 'data_fetched',
                'payload' => [
                    'bank_connection_id' => $connection->id,
                    'sync_type' => $reason,
                    'added_count' => $addedCount,
                    'modified_count' => $modifiedCount,
                    'removed_count' => $removedCount,
                ],
                'performed_at' => now(),
            ]);

            return $run;
        } catch (\Throwable $exception) {
            $syncFailures = $connection->sync_failures + 1;

            $connection->update([
                'sync_failures' => $syncFailures,
                'last_sync_error' => $exception->getMessage(),
                'error_message' => $exception->getMessage(),
                'status' => $syncFailures >= 3 ? 'error' : 'sync_failed',
                'next_sync_at' => now()->addMinutes(30),
            ]);

            $failedRun = $connection->syncRuns()->create([
                'sync_type' => $reason,
                'status' => 'failed',
                'cursor_before' => $connection->plaid_cursor,
                'cursor_after' => $connection->plaid_cursor,
                'started_at' => now(),
                'finished_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            return $failedRun;
        } finally {
            $lock->release();
        }
    }

    public function refreshTransactions(BankConnection $connection): void
    {
        $this->plaidClient->refreshTransactions((string) $connection->plaid_access_token_encrypted);
    }

    public function refreshAllUserConnections(User $user): int
    {
        $connectionIds = $user->bankConnections()
            ->where('provider', 'plaid')
            ->whereIn('status', ['connected', 'sync_failed', 'syncing'])
            ->pluck('id');

        foreach ($connectionIds as $connectionId) {
            SyncPlaidConnectionJob::dispatch((int) $connectionId, 'manual_all', false);
        }

        return $connectionIds->count();
    }

    public function handleWebhook(array $payload): void
    {
        $this->webhookService->handleWebhook($payload);
    }

    private function syncAccounts(BankConnection $connection): array
    {
        $response = $this->plaidClient->getAccounts((string) $connection->plaid_access_token_encrypted);
        $accounts = collect(Arr::get($response, 'accounts', []));
        $accountsByPlaidId = [];

        $accounts->each(function (array $account) use ($connection, &$accountsByPlaidId): void {
            $plaidAccountId = (string) Arr::get($account, 'account_id');

            if ($plaidAccountId === '') {
                return;
            }

            $bankAccount = BankAccount::updateOrCreate(
                [
                    'bank_connection_id' => $connection->id,
                    'plaid_account_id' => $plaidAccountId,
                ],
                [
                    'external_uid' => $plaidAccountId,
                    'name' => Arr::get($account, 'name'),
                    'official_name' => Arr::get($account, 'official_name'),
                    'mask_encrypted' => Arr::get($account, 'mask'),
                    'type' => Arr::get($account, 'type'),
                    'subtype' => Arr::get($account, 'subtype'),
                    'currency' => Arr::get($account, 'balances.iso_currency_code'),
                    'currency_code' => Arr::get($account, 'balances.iso_currency_code'),
                    'balances_encrypted' => Arr::get($account, 'balances', []),
                    'raw_encrypted' => $account,
                    'is_active' => true,
                    'last_synced_at' => now(),
                ],
            );

            $accountsByPlaidId[$plaidAccountId] = $bankAccount->id;
        });

        return $accountsByPlaidId;
    }

    private function upsertTransaction(BankConnection $connection, array $transaction, array $accountsByPlaidId): int
    {
        $plaidAccountId = (string) Arr::get($transaction, 'account_id');
        $plaidTransactionId = (string) Arr::get($transaction, 'transaction_id');

        if ($plaidAccountId === '' || $plaidTransactionId === '') {
            return 0;
        }

        $bankAccountId = $accountsByPlaidId[$plaidAccountId] ?? null;

        if (! is_int($bankAccountId)) {
            return 0;
        }

        $bankTransaction = BankTransaction::query()->updateOrCreate(
            [
                'bank_connection_id' => $connection->id,
                'plaid_transaction_id' => $plaidTransactionId,
            ],
            [
                'bank_account_id' => $bankAccountId,
                'external_uid' => $plaidTransactionId,
                'plaid_pending_transaction_id' => Arr::get($transaction, 'pending_transaction_id'),
                'account_owner' => Arr::get($transaction, 'account_owner'),
                'merchant_name' => Arr::get($transaction, 'merchant_name'),
                'payee' => Arr::get($transaction, 'counterparties.0.name'),
                'name' => Arr::get($transaction, 'name'),
                'description' => Arr::get($transaction, 'name'),
                'booked_at' => Arr::get($transaction, 'date'),
                'date' => Arr::get($transaction, 'date'),
                'authorized_date' => Arr::get($transaction, 'authorized_date'),
                'amount' => Arr::get($transaction, 'amount', 0),
                'currency' => Arr::get($transaction, 'iso_currency_code'),
                'iso_currency_code' => Arr::get($transaction, 'iso_currency_code'),
                'unofficial_currency_code' => Arr::get($transaction, 'unofficial_currency_code'),
                'pending' => (bool) Arr::get($transaction, 'pending', false),
                'category' => Arr::get($transaction, 'category', []),
                'personal_finance_category' => Arr::get($transaction, 'personal_finance_category', []),
                'payment_channel' => Arr::get($transaction, 'payment_channel'),
                'location_encrypted' => Arr::get($transaction, 'location', []),
                'raw_encrypted' => $transaction,
                'removed_at' => null,
            ],
        );

        if (! $bankTransaction->category_manually_set && $connection->user !== null) {
            $resolvedCategory = ($this->resolveBankTransactionCategory)($bankTransaction, $connection->user);

            if ($bankTransaction->category_id !== $resolvedCategory?->id) {
                $bankTransaction->forceFill([
                    'category_id' => $resolvedCategory?->id,
                ])->save();
            }
        }

        return 1;
    }
}
