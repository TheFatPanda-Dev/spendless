<?php

namespace App\Services\Ibkr;

use App\Actions\Transactions\ResolveBankTransactionCategory;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankSyncRun;
use App\Models\BankTransaction;
use App\Models\InvestmentActivity;
use App\Services\Tags\TagRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class IbkrSync
{
    /** IBKR's end-of-day data is ready well before this time (UTC). */
    private const DAILY_RUN_HOUR = 7;

    public function __construct(
        private readonly FlexClient $client,
        private readonly ResolveBankTransactionCategory $resolveCategory,
        private readonly TagRules $tagRules,
    ) {}

    public static function nextRun(): CarbonImmutable
    {
        $today = CarbonImmutable::now('UTC')->startOfDay()->addHours(self::DAILY_RUN_HOUR);

        return $today->isFuture() ? $today : $today->addDay();
    }

    public function sync(BankConnection $connection, string $trigger): BankSyncRun
    {
        $run = $connection->syncRuns()->create(['sync_type' => $trigger, 'status' => 'running', 'started_at' => now()]);
        $connection->forceFill(['status' => BankConnection::STATUS_SYNCING])->save();

        try {
            $token = (string) Arr::get($connection->credentials_encrypted ?? [], 'token', '');
            $queryId = (string) Arr::get($connection->metadata ?? [], 'query_id', '');

            if ($token === '' || $queryId === '') {
                throw new FlexException('The Flex token or query ID is missing.', '1015');
            }

            $reports = FlexReportParser::parse($this->client->download($token, $queryId));

            if ($reports === []) {
                throw new FlexException('The IBKR report contains no account. Check that the Flex Query includes Account Information.', '1003');
            }

            $added = 0;
            $updated = 0;

            DB::transaction(function () use ($connection, $reports, &$added, &$updated): void {
                foreach ($reports as $report) {
                    [$accountAdded, $accountUpdated] = $this->store($connection, $report);
                    $added += $accountAdded;
                    $updated += $accountUpdated;
                }
            });

            if ($connection->user !== null) {
                $this->tagRules->applyToNew($connection->user);
            }

            $connection->forceFill([
                'status' => BankConnection::STATUS_CONNECTED,
                'last_synced_at' => now(),
                'next_sync_at' => self::nextRun(),
                'last_sync_error' => null,
                'sync_failures' => 0,
            ])->save();
            $connection->wallet?->forceFill(['last_synced_at' => now()])->save();

            $run->update(['status' => 'succeeded', 'added_count' => $added, 'modified_count' => $updated, 'finished_at' => now()]);
        } catch (FlexException $exception) {
            $this->fail($connection, $run, $exception);
        } catch (Throwable $exception) {
            report($exception);
            $this->fail($connection, $run, new FlexException($exception->getMessage()));
        }

        return $run;
    }

    /**
     * @return array{int, int}
     */
    private function store(BankConnection $connection, FlexReport $report): array
    {
        $account = $connection->accounts()->firstOrNew(['external_uid' => 'ibkr:'.$report->accountId]);
        $balances = is_array($account->balances_encrypted) ? $account->balances_encrypted : [];

        $account->fill([
            'name' => 'IBKR '.($report->accountType ?: 'account'),
            'official_name' => 'Interactive Brokers',
            'mask_encrypted' => substr($report->accountId, -4),
            'type' => 'investment',
            'subtype' => 'brokerage',
            'currency' => $report->baseCurrency,
            'is_active' => true,
            'last_synced_at' => now(),
            'balances_encrypted' => $report->netAssetValue !== null
                ? ['current' => $report->netAssetValue, 'available' => $report->cash ?? $report->netAssetValue, 'as_of' => $report->valueDate]
                : ($balances ?: ['current' => 0, 'available' => 0]),
        ])->save();

        // Holdings are a snapshot: replace them.
        $account->investmentPositions()->delete();

        if ($report->positions !== []) {
            $account->investmentPositions()->createMany($report->positions);
        }

        [$activitiesAdded, $activitiesUpdated] = $this->storeActivities($account, $report->activities);
        [$cashAdded, $cashUpdated] = $this->storeCashTransactions($connection, $account, $report->cashTransactions);

        return [$activitiesAdded + $cashAdded, $activitiesUpdated + $cashUpdated];
    }

    /**
     * @param  list<array<string, mixed>>  $activities
     * @return array{int, int}
     */
    private function storeActivities(BankAccount $account, array $activities): array
    {
        if ($activities === []) {
            return [0, 0];
        }

        $existing = $account->investmentActivities()->whereIn('external_id', array_column($activities, 'external_id'))->count();
        $rows = array_map(fn (array $activity): array => [
            ...$activity,
            'description' => $activity['description'] !== null ? Str::limit((string) $activity['description'], 250, '') : null,
            'bank_account_id' => $account->id,
        ], $activities);

        foreach (array_chunk($rows, 400) as $chunk) {
            InvestmentActivity::query()->upsert(
                $chunk,
                ['bank_account_id', 'external_id'],
                ['kind', 'date', 'symbol', 'description', 'quantity', 'price', 'amount', 'commission', 'currency', 'fx_rate_to_base', 'updated_at'],
            );
        }

        return [count($activities) - $existing, $existing];
    }

    /**
     * Dividends, interest, fees and taxes, as income and expenses.
     *
     * @param  list<array<string, mixed>>  $transactions
     * @return array{int, int}
     */
    private function storeCashTransactions(BankConnection $connection, BankAccount $account, array $transactions): array
    {
        if ($transactions === []) {
            return [0, 0];
        }

        $existingUids = array_flip($account->transactions()
            ->whereIn('external_uid', array_column($transactions, 'external_uid'))
            ->pluck('external_uid')
            ->all());

        $rows = [];

        foreach ($transactions as $transaction) {
            $record = [
                ...$transaction,
                'name' => Str::limit((string) $transaction['name'], 250, ''),
                'merchant_name' => $transaction['merchant_name'] !== null ? Str::limit((string) $transaction['merchant_name'], 250, '') : null,
                'bank_connection_id' => $connection->id,
                'bank_account_id' => $account->id,
                'pending' => 0,
                'category_id' => null,
                'category_manually_set' => 0,
            ];

            if (! isset($existingUids[$transaction['external_uid']]) && $connection->user !== null) {
                $record['category_id'] = ($this->resolveCategory)(new BankTransaction($transaction), $connection->user)?->id;
            }

            $rows[] = $record;
        }

        foreach (array_chunk($rows, 400) as $chunk) {
            BankTransaction::query()->upsert(
                $chunk,
                ['bank_account_id', 'external_uid'],
                ['name', 'merchant_name', 'description', 'amount', 'currency', 'date', 'updated_at'],
            );
        }

        return [count($transactions) - count($existingUids), count($existingUids)];
    }

    private function fail(BankConnection $connection, BankSyncRun $run, FlexException $exception): void
    {
        $newToken = $exception->needsNewToken();

        $connection->forceFill([
            'status' => $newToken ? BankConnection::STATUS_NEEDS_REAUTH : BankConnection::STATUS_SYNC_FAILED,
            'last_sync_error' => mb_substr($exception->friendlyMessage(), 0, 1000),
            'next_sync_at' => match (true) {
                $newToken => null,
                $exception->shouldRetryLater() => now()->addMinutes(20),
                default => self::nextRun(),
            },
            'sync_failures' => $connection->sync_failures + 1,
        ])->save();

        $run->update([
            'status' => 'failed',
            'error_message' => mb_substr(($exception->errorCode ? '['.$exception->errorCode.'] ' : '').$exception->getMessage(), 0, 1000),
            'finished_at' => now(),
        ]);
    }
}
