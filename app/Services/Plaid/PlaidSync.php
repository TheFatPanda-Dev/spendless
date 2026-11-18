<?php

namespace App\Services\Plaid;

use App\Actions\Transactions\ResolveBankTransactionCategory;
use App\Models\BankConnection;
use App\Models\BankSyncRun;
use App\Models\BankTransaction;
use App\Services\EnableBanking\SyncSchedule;
use App\Services\Tags\TagRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pulls a Plaid Item's accounts, balances and transaction changes with /transactions/sync.
 * One call per page (500 transactions); balances come in the same response.
 */
class PlaidSync
{
    private const MAX_PAGES = 20;

    private const UPSERT_CHUNK = 400;

    /** Right after connecting, Plaid may still be fetching the history. */
    private const NOT_READY_RETRY_MINUTES = 10;

    private const NOT_READY_MAX_ATTEMPTS = 6;

    public function __construct(
        private readonly PlaidClient $client,
        private readonly ResolveBankTransactionCategory $resolveCategory,
        private readonly TagRules $tagRules,
    ) {}

    /**
     * Once a day by default (PLAID_SYNC_HOURS), to make the free allowance last.
     */
    public static function nextRun(?CarbonImmutable $from = null): CarbonImmutable
    {
        return SyncSchedule::nextSlot($from, SyncSchedule::parseHours((string) config('services.plaid.sync_hours', '7'), [7]));
    }

    /**
     * @param  string  $trigger  scheduled | manual | connected
     */
    public function sync(BankConnection $connection, string $trigger): BankSyncRun
    {
        $run = $connection->syncRuns()->create(['sync_type' => $trigger, 'status' => 'running', 'started_at' => now()]);

        if (PlaidUsage::exhausted(userPresent: $trigger !== 'scheduled')) {
            $connection->forceFill([
                'status' => BankConnection::STATUS_SYNC_FAILED,
                'last_sync_error' => sprintf(
                    "Plaid's free allowance (%d calls) is used up, so this bank isn't syncing. Import a statement instead, or raise PLAID_CALL_LIMIT on a paid Plaid plan.",
                    PlaidUsage::limit(),
                ),
                'next_sync_at' => null,
            ])->save();
            $run->update(['status' => 'failed', 'error_message' => 'Plaid call allowance used up.', 'finished_at' => now()]);

            return $run;
        }

        $connection->forceFill(['status' => BankConnection::STATUS_SYNCING])->save();

        try {
            $token = (string) Arr::get($connection->credentials_encrypted ?? [], 'access_token', '');

            if ($token === '') {
                throw new PlaidException('The Plaid access token is missing.', 'INVALID_INPUT', 'INVALID_ACCESS_TOKEN');
            }

            $metadata = $connection->metadata ?? [];
            $startCursor = isset($metadata['cursor']) && is_string($metadata['cursor']) ? $metadata['cursor'] : null;
            $changes = $this->fetchChanges($token, $startCursor);
            $notReady = $changes['status'] === 'NOT_READY'
                && $changes['added'] === [] && $changes['modified'] === [] && $changes['removed'] === []
                && ($startCursor === null || $startCursor === '');

            $connection->loadMissing('user');
            [$added, $updated, $removed] = DB::transaction(function () use ($connection, $changes): array {
                $accountIds = $this->storeAccounts($connection, $changes['accounts']);
                [$added, $updated] = $this->storeTransactions($connection, $accountIds, [...$changes['added'], ...$changes['modified']]);
                $removed = $this->removeTransactions($accountIds, $changes['removed'], $changes['added']);

                return [$added, $updated, $removed];
            });

            if ($connection->user !== null) {
                $this->tagRules->applyToNew($connection->user);
            }

            $attempts = $notReady ? (int) ($metadata['not_ready_attempts'] ?? 0) + 1 : 0;

            $connection->forceFill([
                'status' => BankConnection::STATUS_CONNECTED,
                'last_synced_at' => now(),
                'next_sync_at' => $notReady && $attempts <= self::NOT_READY_MAX_ATTEMPTS
                    ? now()->addMinutes(self::NOT_READY_RETRY_MINUTES)
                    : self::nextRun(),
                'last_sync_error' => null,
                'sync_failures' => 0,
                'metadata' => [
                    ...$metadata,
                    // Plaid: don't keep the cursor of a response sent before the data was ready.
                    'cursor' => $notReady ? $startCursor : $changes['cursor'],
                    'not_ready_attempts' => $attempts,
                ],
            ])->save();
            $connection->wallet?->forceFill(['last_synced_at' => now()])->save();

            $run->update([
                'status' => 'succeeded',
                'added_count' => $added,
                'modified_count' => $updated,
                'removed_count' => $removed,
                'finished_at' => now(),
            ]);
        } catch (PlaidException $exception) {
            $this->fail($connection, $run, $exception);
        } catch (Throwable $exception) {
            report($exception);
            $this->fail($connection, $run, new PlaidException($exception->getMessage(), previous: $exception));
        }

        return $run;
    }

    /**
     * @return array{added: list<array<string, mixed>>, modified: list<array<string, mixed>>, removed: list<array<string, mixed>>, accounts: array<string, array<string, mixed>>, cursor: string|null, status: string|null}
     */
    private function fetchChanges(string $token, ?string $startCursor): array
    {
        for ($attempt = 1; ; $attempt++) {
            $changes = ['added' => [], 'modified' => [], 'removed' => [], 'accounts' => [], 'cursor' => $startCursor, 'status' => null];
            $pages = 0;

            try {
                do {
                    $page = $this->client->transactionsSync($token, $changes['cursor']);

                    foreach (['added', 'modified', 'removed'] as $key) {
                        foreach (Arr::wrap($page[$key] ?? []) as $item) {
                            if (is_array($item)) {
                                $changes[$key][] = $item;
                            }
                        }
                    }

                    foreach (Arr::wrap($page['accounts'] ?? []) as $account) {
                        if (is_array($account) && isset($account['account_id'])) {
                            $changes['accounts'][(string) $account['account_id']] = $account;
                        }
                    }

                    $changes['cursor'] = isset($page['next_cursor']) ? (string) $page['next_cursor'] : $changes['cursor'];
                    $changes['status'] = isset($page['transactions_update_status']) ? (string) $page['transactions_update_status'] : null;
                    $pages++;
                } while (($page['has_more'] ?? false) === true && $pages < self::MAX_PAGES);

                return $changes;
            } catch (PlaidException $exception) {
                // Plaid's instruction: restart the whole pagination from the original cursor.
                if (! $exception->isMutationDuringPagination() || $attempt >= 2) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $plaidAccounts
     * @return array<string, int> Plaid account_id => local account id, for every account of the Item.
     */
    private function storeAccounts(BankConnection $connection, array $plaidAccounts): array
    {
        foreach ($plaidAccounts as $plaidId => $plaidAccount) {
            $account = $connection->accounts()->firstOrNew(['external_uid' => 'plaid:'.$plaidId]);
            $balances = (array) ($plaidAccount['balances'] ?? []);
            $isDebt = in_array($plaidAccount['type'] ?? null, ['credit', 'loan'], true);
            $current = isset($balances['current']) && is_numeric($balances['current']) ? (float) $balances['current'] : null;
            $available = isset($balances['available']) && is_numeric($balances['available']) ? (float) $balances['available'] : null;
            $previous = is_array($account->balances_encrypted) ? $account->balances_encrypted : [];

            $account->fill([
                'name' => Str::limit((string) ($plaidAccount['name'] ?? 'Account'), 120, ''),
                'official_name' => isset($plaidAccount['official_name']) ? Str::limit((string) $plaidAccount['official_name'], 250, '') : null,
                'mask_encrypted' => isset($plaidAccount['mask']) ? (string) $plaidAccount['mask'] : null,
                'type' => $plaidAccount['type'] ?? null,
                'subtype' => $plaidAccount['subtype'] ?? null,
                'currency' => strtoupper((string) ($balances['iso_currency_code'] ?? $balances['unofficial_currency_code'] ?? $account->currency ?? 'GBP')),
                // Money owed on a card or loan counts against the total: stored as negative.
                'balances_encrypted' => [
                    'current' => $current !== null ? round($isDebt ? -$current : $current, 2) : ($previous['current'] ?? 0),
                    'available' => $isDebt ? null : ($available !== null ? round($available, 2) : null),
                    'credit_limit' => $isDebt && isset($balances['limit']) && is_numeric($balances['limit']) ? (float) $balances['limit'] : null,
                ],
                'is_active' => true,
                'last_synced_at' => now(),
            ])->save();
        }

        return $connection->accounts()
            ->where('external_uid', 'like', 'plaid:%')
            ->pluck('id', 'external_uid')
            ->mapWithKeys(fn ($id, string $uid): array => [Str::after($uid, 'plaid:') => (int) $id])
            ->all();
    }

    /**
     * @param  array<string, int>  $accountIds
     * @param  list<array<string, mixed>>  $transactions  Added and modified.
     * @return array{int, int}
     */
    private function storeTransactions(BankConnection $connection, array $accountIds, array $transactions): array
    {
        $rows = [];

        foreach ($transactions as $transaction) {
            $accountId = $accountIds[(string) ($transaction['account_id'] ?? '')] ?? null;
            $mapped = self::map($transaction);

            if ($accountId !== null && $mapped !== null) {
                $rows[$accountId.'|'.$mapped['external_uid']] = [...$mapped, 'bank_account_id' => $accountId];
            }
        }

        if ($rows === []) {
            return [0, 0];
        }

        $existing = BankTransaction::query()
            ->whereIn('bank_account_id', array_values($accountIds))
            ->whereIn('external_uid', array_column($rows, 'external_uid'))
            ->get(['bank_account_id', 'external_uid'])
            ->map(fn (BankTransaction $transaction): string => $transaction->bank_account_id.'|'.$transaction->external_uid)
            ->flip();
        $records = [];

        foreach ($rows as $key => $row) {
            $isNew = ! $existing->has($key);

            $records[] = [
                ...$row,
                'bank_connection_id' => $connection->id,
                'pending' => $row['pending'] ? 1 : 0,
                // Categorise once on arrival; never overwrite a category later.
                'category_id' => $isNew && $connection->user !== null
                    ? ($this->resolveCategory)(new BankTransaction($row), $connection->user)?->id
                    : null,
                'category_manually_set' => 0,
            ];
        }

        foreach (array_chunk($records, self::UPSERT_CHUNK) as $chunk) {
            BankTransaction::query()->upsert(
                $chunk,
                ['bank_account_id', 'external_uid'],
                ['bank_connection_id', 'name', 'merchant_name', 'payee', 'description', 'amount', 'currency', 'date', 'pending', 'updated_at'],
            );
        }

        $added = count($records) - $existing->count();

        return [$added, count($records) - $added];
    }

    /**
     * Removed transactions are usually pending ones that have now posted under a new id:
     * the posted one keeps the category and tags the user gave the pending one.
     *
     * @param  array<string, int>  $accountIds
     * @param  list<array<string, mixed>>  $removed
     * @param  list<array<string, mixed>>  $added
     */
    private function removeTransactions(array $accountIds, array $removed, array $added): int
    {
        $removedUids = array_values(array_filter(array_map(
            fn (array $item): ?string => isset($item['transaction_id']) ? (string) $item['transaction_id'] : null,
            $removed,
        )));

        if ($removedUids === [] || $accountIds === []) {
            return 0;
        }

        $old = BankTransaction::query()
            ->whereIn('bank_account_id', array_values($accountIds))
            ->whereIn('external_uid', $removedUids)
            ->with('tags:id')
            ->get()
            ->keyBy('external_uid');

        foreach ($added as $transaction) {
            $pendingId = $transaction['pending_transaction_id'] ?? null;
            $previous = is_string($pendingId) ? $old->get($pendingId) : null;

            if (! $previous instanceof BankTransaction) {
                continue;
            }

            $posted = BankTransaction::query()
                ->where('bank_account_id', $previous->bank_account_id)
                ->where('external_uid', (string) $transaction['transaction_id'])
                ->first();

            if ($posted === null) {
                continue;
            }

            $posted->forceFill([
                'category_id' => $previous->category_manually_set ? $previous->category_id : $posted->category_id,
                'category_manually_set' => $previous->category_manually_set,
                // Already checked by the tag rules as a pending payment: don't re-tag.
                'tag_rules_applied' => $previous->tag_rules_applied,
            ])->save();
            $posted->tags()->syncWithoutDetaching($previous->tags->modelKeys());
        }

        return BankTransaction::query()
            ->whereIn('bank_account_id', array_values($accountIds))
            ->whereIn('external_uid', $removedUids)
            ->delete();
    }

    /**
     * Plaid amounts are positive for money out; SpendLess stores money out as negative.
     *
     * @param  array<string, mixed>  $transaction
     * @return array{external_uid: string, name: string, merchant_name: string|null, payee: string|null, description: string|null, amount: float, currency: string, date: string, pending: bool}|null
     */
    public static function map(array $transaction): ?array
    {
        $id = $transaction['transaction_id'] ?? null;
        $date = $transaction['date'] ?? $transaction['authorized_date'] ?? null;

        if (! is_string($id) || $id === '' || ! is_numeric($transaction['amount'] ?? null) || ! is_string($date)) {
            return null;
        }

        $counterparty = collect(Arr::wrap($transaction['counterparties'] ?? []))
            ->first(fn ($party): bool => is_array($party) && filled($party['name'] ?? null));
        $payee = is_array($counterparty) ? (string) $counterparty['name'] : null;
        $merchant = filled($transaction['merchant_name'] ?? null) ? (string) $transaction['merchant_name'] : $payee;
        $name = (string) ($transaction['name'] ?? $transaction['original_description'] ?? 'Transaction');

        return [
            'external_uid' => $id,
            'name' => Str::limit($name, 250, ''),
            'merchant_name' => $merchant !== null ? Str::limit($merchant, 250, '') : null,
            'payee' => $payee !== null ? Str::limit($payee, 250, '') : null,
            'description' => filled($transaction['original_description'] ?? null) ? Str::limit((string) $transaction['original_description'], 1000, '') : null,
            'amount' => round(-1 * (float) $transaction['amount'], 2),
            'currency' => strtoupper((string) ($transaction['iso_currency_code'] ?? $transaction['unofficial_currency_code'] ?? 'GBP')),
            'date' => $date,
            'pending' => (bool) ($transaction['pending'] ?? false),
        ];
    }

    private function fail(BankConnection $connection, BankSyncRun $run, PlaidException $exception): void
    {
        $reauth = $exception->requiresReauthorization();

        $connection->forceFill([
            'status' => $reauth ? BankConnection::STATUS_NEEDS_REAUTH : BankConnection::STATUS_SYNC_FAILED,
            'last_sync_error' => mb_substr($exception->friendlyMessage(), 0, 1000),
            'next_sync_at' => $reauth ? null : self::nextRun(),
            'sync_failures' => $exception->isRateLimited() ? $connection->sync_failures : $connection->sync_failures + 1,
        ])->save();

        $run->update([
            'status' => 'failed',
            'error_message' => mb_substr($exception->getMessage(), 0, 1000),
            'finished_at' => now(),
        ]);
    }
}
