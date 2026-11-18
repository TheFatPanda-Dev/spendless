<?php

namespace App\Services\Imports;

use App\Actions\Transactions\ResolveBankTransactionCategory;
use App\Models\BankAccount;
use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\User;
use App\Services\Tags\TagRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StatementImporter
{
    private const CHUNK = 400;

    public function __construct(
        private readonly ResolveBankTransactionCategory $resolveCategory,
        private readonly TagRules $tagRules,
    ) {}

    /**
     * @param  BankAccount|null  $account  Null when the account will be created on confirm.
     */
    public function plan(ParsedStatement $statement, ?BankAccount $account, string $currency): ImportPlan
    {
        $currency = strtoupper($currency);
        $records = [];
        $cancelled = [];
        $occurrences = [];
        $skipped = 0;

        foreach ($statement->transactions as $transaction) {
            if ($transaction->currency !== null && strtoupper($transaction->currency) !== $currency) {
                $skipped++;

                continue;
            }

            $uid = $this->uid($transaction, $statement->format, $occurrences);

            if ($transaction->cancelled) {
                $cancelled[] = $uid;

                continue;
            }

            $records[$uid] = [
                'external_uid' => $uid,
                'name' => Str::limit($transaction->description, 250, ''),
                'merchant_name' => $transaction->payee !== null ? Str::limit($transaction->payee, 250, '') : null,
                'description' => $transaction->description,
                'amount' => $transaction->amount,
                'currency' => $currency,
                'date' => $transaction->date,
                'pending' => $transaction->pending,
            ];
        }

        $dates = array_column($records, 'date');
        $dateFrom = $dates === [] ? null : min($dates);
        $dateTo = $dates === [] ? null : max($dates);

        if ($account === null || ! $account->exists) {
            return new ImportPlan($records, [], [], [], $skipped, $dateFrom, $dateTo);
        }

        $existingUids = [];

        foreach (array_chunk([...array_keys($records), ...$cancelled], self::CHUNK) as $chunk) {
            foreach ($account->transactions()->whereIn('external_uid', $chunk)->pluck('external_uid') as $uid) {
                $existingUids[(string) $uid] = true;
            }
        }

        $stalePending = $dateFrom === null ? [] : $account->transactions()
            ->where('pending', true)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->get(['id', 'external_uid'])
            ->reject(fn (BankTransaction $row): bool => isset($records[$row->external_uid]))
            ->pluck('id')
            ->all();

        return new ImportPlan(
            records: $records,
            existingUids: array_intersect_key($existingUids, $records),
            cancelledUids: array_values(array_filter($cancelled, fn (string $uid): bool => isset($existingUids[$uid]))),
            stalePendingIds: array_map('intval', $stalePending),
            skipped: $skipped,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
        );
    }

    public function apply(
        ImportPlan $plan,
        ParsedStatement $statement,
        BankAccount $account,
        User $user,
        string $filename,
        ?float $balanceOverride = null,
    ): BankStatementImport {
        return DB::transaction(function () use ($plan, $statement, $account, $user, $filename, $balanceOverride): BankStatementImport {
            $connection = $account->connection;
            $rows = [];

            foreach ($plan->records as $uid => $record) {
                $isNew = ! isset($plan->existingUids[$uid]);
                $categoryId = null;

                if ($isNew) {
                    $categoryId = ($this->resolveCategory)(new BankTransaction($record), $user)?->id;
                }

                $rows[] = [
                    ...$record,
                    'bank_connection_id' => $account->bank_connection_id,
                    'bank_account_id' => $account->id,
                    'pending' => $record['pending'] ? 1 : 0,
                    'category_id' => $categoryId,
                    'category_manually_set' => 0,
                ];
            }

            foreach (array_chunk($rows, self::CHUNK) as $chunk) {
                // category_id is not in the update list: re-imports never touch categories.
                BankTransaction::query()->upsert(
                    $chunk,
                    ['bank_account_id', 'external_uid'],
                    ['name', 'merchant_name', 'description', 'amount', 'currency', 'date', 'pending', 'updated_at'],
                );
            }

            if ($plan->cancelledUids !== []) {
                $account->transactions()->whereIn('external_uid', $plan->cancelledUids)->delete();
            }

            if ($plan->stalePendingIds !== []) {
                $account->transactions()->whereIn('id', $plan->stalePendingIds)->delete();
            }

            $this->tagRules->applyToNew($user);

            $this->updateBalance($account, $plan, $statement, $balanceOverride);

            $account->forceFill(['last_synced_at' => now()])->save();
            $connection?->forceFill(['last_synced_at' => now()])->save();
            $connection?->wallet?->forceFill(['last_synced_at' => now()])->save();

            return $account->statementImports()->create([
                'user_id' => $user->id,
                'original_filename' => Str::limit($filename, 250, ''),
                'format' => $statement->format,
                'rows_total' => count($plan->records),
                'rows_added' => $plan->newCount(),
                'rows_updated' => $plan->updatedCount(),
                'rows_removed' => $plan->removedCount(),
                'rows_skipped' => $plan->skipped,
                'date_from' => $plan->dateFrom,
                'date_to' => $plan->dateTo,
            ]);
        });
    }

    private function updateBalance(BankAccount $account, ImportPlan $plan, ParsedStatement $statement, ?float $override): void
    {
        $balances = is_array($account->balances_encrypted) ? $account->balances_encrypted : [];
        $knownAsOf = isset($balances['as_of']) ? (string) $balances['as_of'] : null;

        if ($statement->closingBalance !== null && $statement->balanceDate !== null) {
            // Never let an older statement roll the balance back.
            if ($knownAsOf === null || $statement->balanceDate >= $knownAsOf) {
                $balances = [
                    'current' => $statement->closingBalance,
                    'available' => $statement->availableBalance ?? $statement->closingBalance,
                    'as_of' => $statement->balanceDate,
                ];
            }
        } elseif ($override !== null) {
            $balances = ['current' => round($override, 2), 'available' => round($override, 2), 'as_of' => now()->toDateString()];
        } else {
            $current = round((float) ($balances['current'] ?? 0) + $plan->newTotal(), 2);
            $balances = [...$balances, 'current' => $current, 'available' => $current];
        }

        $account->forceFill(['balances_encrypted' => $balances])->save();
    }

    /**
     * @param  array<string, int>  $occurrences
     */
    private function uid(ParsedTransaction $transaction, string $format, array &$occurrences): string
    {
        if ($transaction->externalId !== null && $transaction->externalId !== '') {
            return Str::limit(($format === 'ofx' ? 'ofx:' : 'ref:').$transaction->externalId, 250, '');
        }

        $fingerprint = $transaction->fingerprint ?? implode('|', [
            $transaction->date,
            number_format($transaction->amount, 2, '.', ''),
            mb_strtolower(preg_replace('/\s+/', ' ', trim($transaction->description)) ?? ''),
        ]);

        $hash = hash('sha256', $fingerprint);
        $occurrences[$hash] = ($occurrences[$hash] ?? 0) + 1;

        // Identical rows on the same day (two coffees) stay separate, and get the same
        // numbers again when the same day appears in a later, overlapping file.
        return 'h:'.$hash.':'.$occurrences[$hash];
    }
}
