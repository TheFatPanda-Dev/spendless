<?php

namespace App\Services\EnableBanking;

use App\Actions\Transactions\ResolveBankTransactionCategory;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankSyncRun;
use App\Models\BankTransaction;
use App\Services\Tags\TagRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pulls balances and transactions for every account of one Enable Banking connection.
 */
class EnableBankingSync
{
    private const MAX_PAGES = 200;

    private const UPSERT_CHUNK = 400;

    /** Re-read this many days before the last sync to catch transactions booked late. */
    private const LATE_BOOKING_DAYS = 7;

    public function __construct(
        private readonly EnableBankingClient $client,
        private readonly ResolveBankTransactionCategory $resolveCategory,
        private readonly TagRules $tagRules,
    ) {}

    /**
     * @param  string  $trigger  scheduled | manual | connected
     * @param  array<string, string>  $psuHeaders  Empty for unattended background syncs.
     */
    public function sync(BankConnection $connection, string $trigger, array $psuHeaders = []): BankSyncRun
    {
        $run = $connection->syncRuns()->create([
            'sync_type' => $trigger,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $connection->forceFill(['status' => BankConnection::STATUS_SYNCING])->save();
        $added = 0;
        $updated = 0;
        $removed = 0;

        try {
            if ($connection->consent_valid_until?->isPast()) {
                throw new EnableBankingException('Bank access has expired.', 'EXPIRED_SESSION');
            }

            $connection->loadMissing(['accounts', 'user']);

            foreach ($connection->accounts->where('is_active', true) as $account) {
                [$accountAdded, $accountUpdated, $accountRemoved] = $this->syncAccount($connection, $account, $psuHeaders);
                $added += $accountAdded;
                $updated += $accountUpdated;
                $removed += $accountRemoved;
            }

            if ($connection->user !== null) {
                $this->tagRules->applyToNew($connection->user);
            }

            $connection->forceFill([
                'status' => BankConnection::STATUS_CONNECTED,
                'last_synced_at' => now(),
                'next_sync_at' => SyncSchedule::nextSlot(),
                'last_sync_error' => null,
                'sync_failures' => 0,
            ])->save();

            $connection->wallet?->forceFill(['last_synced_at' => now()])->save();

            $run->update([
                'status' => 'succeeded',
                'added_count' => $added,
                'modified_count' => $updated,
                'removed_count' => $removed,
                'finished_at' => now(),
            ]);
        } catch (EnableBankingException $exception) {
            $this->recordFailure($connection, $run, $exception);
        } catch (Throwable $exception) {
            report($exception);
            $this->recordFailure($connection, $run, new EnableBankingException($exception->getMessage(), previous: $exception));
        }

        return $run;
    }

    /**
     * @param  array<string, string>  $psuHeaders
     * @return array{int, int, int}
     */
    private function syncAccount(BankConnection $connection, BankAccount $account, array $psuHeaders): array
    {
        $balances = $this->withPsuFallback(
            fn (array $headers): array => $this->client->getBalances($account->external_uid, $headers),
            $psuHeaders,
        );

        $dateFrom = $account->last_synced_at !== null
            ? CarbonImmutable::instance($account->last_synced_at)->subDays(self::LATE_BOOKING_DAYS)
            : now()->subDays(max(1, (int) config('services.enable_banking.initial_history_days', 90)));

        $mapper = new EnableBankingTransactionMapper;
        $currency = (string) ($account->currency ?: 'EUR');
        $rows = [];
        $continuationKey = null;
        $seenKeys = [];
        $pages = 0;

        do {
            $query = ['date_from' => $dateFrom->toDateString()];

            if ($continuationKey !== null) {
                $query['continuation_key'] = $continuationKey;
            }

            $response = $this->withPsuFallback(
                fn (array $headers): array => $this->client->getTransactions($account->external_uid, $query, $headers),
                $psuHeaders,
            );

            foreach ($response['transactions'] ?? [] as $transaction) {
                $mapped = is_array($transaction) ? $mapper->map($transaction, $currency) : null;

                if ($mapped !== null) {
                    $rows[$mapped['external_uid']] = $mapped;
                }
            }

            $seenKeys[] = $continuationKey;
            $nextKey = $response['continuation_key'] ?? null;
            $continuationKey = is_string($nextKey) && $nextKey !== '' && ! in_array($nextKey, $seenKeys, true)
                ? $nextKey
                : null;
            $pages++;
        } while ($continuationKey !== null && $pages < self::MAX_PAGES);

        return DB::transaction(function () use ($connection, $account, $rows, $balances, $currency): array {
            [$added, $updated] = $this->storeTransactions($connection, $account, $rows);

            // Pending rows the bank no longer reports were either booked (under a new
            // reference) or cancelled; the booked version is already in $rows.
            $removed = $account->transactions()
                ->where('pending', true)
                ->whereNotIn('external_uid', array_keys($rows))
                ->delete();

            $picked = EnableBankingTransactionMapper::pickBalances($balances, $currency);

            $account->forceFill([
                'balances_encrypted' => $picked ?? $account->balances_encrypted,
                'last_synced_at' => now(),
            ])->save();

            return [$added, $updated, $removed];
        });
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @return array{int, int}
     */
    private function storeTransactions(BankConnection $connection, BankAccount $account, array $rows): array
    {
        if ($rows === []) {
            return [0, 0];
        }

        $existingUids = [];

        foreach (array_chunk(array_keys($rows), self::UPSERT_CHUNK) as $uidChunk) {
            $existingUids = [
                ...$existingUids,
                ...$account->transactions()->whereIn('external_uid', $uidChunk)->pluck('external_uid')->all(),
            ];
        }

        $existingLookup = array_flip($existingUids);
        $records = [];

        foreach ($rows as $uid => $row) {
            $record = [
                ...$row,
                'bank_connection_id' => $connection->id,
                'bank_account_id' => $account->id,
                'pending' => $row['pending'] ? 1 : 0,
                'category_id' => null,
                'category_manually_set' => 0,
            ];

            if (! array_key_exists($uid, $existingLookup) && $connection->user !== null) {
                // Categorise once when the transaction arrives instead of on every page view.
                $record['category_id'] = ($this->resolveCategory)(new BankTransaction($row), $connection->user)?->id;
            }

            $records[] = $record;
        }

        foreach (array_chunk($records, self::UPSERT_CHUNK) as $chunk) {
            // category_id is left out of the update list so existing (and user-chosen)
            // categories are never overwritten by a re-sync.
            BankTransaction::query()->upsert(
                $chunk,
                ['bank_account_id', 'external_uid'],
                ['bank_connection_id', 'name', 'merchant_name', 'description', 'amount', 'currency', 'date', 'pending', 'updated_at'],
            );
        }

        $updated = count($existingLookup);

        return [count($rows) - $updated, $updated];
    }

    /**
     * Some banks insist on an exact set of PSU headers. If ours are refused, retry once
     * without them (the request then counts as an unattended refresh).
     *
     * @template T
     *
     * @param  callable(array<string, string>): T  $call
     * @param  array<string, string>  $psuHeaders
     * @return T
     */
    private function withPsuFallback(callable $call, array $psuHeaders): mixed
    {
        try {
            return $call($psuHeaders);
        } catch (EnableBankingException $exception) {
            if ($psuHeaders === [] || ! in_array($exception->errorCode, ['PSU_HEADER_NOT_PROVIDED', 'PSU_HEADER_INVALID'], true)) {
                throw $exception;
            }

            return $call([]);
        }
    }

    private function recordFailure(BankConnection $connection, BankSyncRun $run, EnableBankingException $exception): void
    {
        $needsReauth = $exception->requiresReauthorization();

        $message = match (true) {
            $needsReauth => 'Bank access has expired or was revoked. Reconnect the bank to keep syncing.',
            $exception->isRateLimited() => 'The bank limited how often it can be refreshed. Trying again at the next sync.',
            default => 'Sync failed: '.$exception->getMessage(),
        };

        $connection->forceFill([
            'status' => $needsReauth ? BankConnection::STATUS_NEEDS_REAUTH : BankConnection::STATUS_SYNC_FAILED,
            'last_sync_error' => mb_substr($message, 0, 1000),
            'next_sync_at' => $needsReauth ? null : SyncSchedule::nextSlot(),
            'sync_failures' => $exception->isRateLimited() ? $connection->sync_failures : $connection->sync_failures + 1,
        ])->save();

        $run->update([
            'status' => 'failed',
            'error_message' => mb_substr($exception->getMessage(), 0, 1000),
            'finished_at' => now(),
        ]);
    }
}
