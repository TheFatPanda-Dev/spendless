<?php

namespace App\Services\Imports;

/**
 * What importing a statement into an account would change. Built for the preview and
 * then applied as-is, so the preview and the result always agree.
 */
final class ImportPlan
{
    /**
     * @param  array<string, array<string, mixed>>  $records  Rows to insert or update, keyed by external uid.
     * @param  array<string, true>  $existingUids  Uids already stored for the account.
     * @param  list<string>  $cancelledUids  Previously imported rows the bank has since cancelled.
     * @param  list<int>  $stalePendingIds  Pending rows that the new file no longer contains.
     */
    public function __construct(
        public readonly array $records,
        public readonly array $existingUids,
        public readonly array $cancelledUids,
        public readonly array $stalePendingIds,
        public readonly int $skipped,
        public readonly ?string $dateFrom,
        public readonly ?string $dateTo,
    ) {}

    public function newCount(): int
    {
        return count(array_diff_key($this->records, $this->existingUids));
    }

    public function updatedCount(): int
    {
        return count(array_intersect_key($this->records, $this->existingUids));
    }

    public function removedCount(): int
    {
        return count($this->cancelledUids) + count($this->stalePendingIds);
    }

    /**
     * Newest new rows first, for the preview table.
     *
     * @return list<array<string, mixed>>
     */
    public function sample(int $limit = 15): array
    {
        $new = array_values(array_diff_key($this->records, $this->existingUids));
        usort($new, fn (array $a, array $b): int => strcmp((string) $b['date'], (string) $a['date']));

        return array_map(fn (array $row): array => [
            'date' => $row['date'],
            'description' => $row['name'],
            'amount' => $row['amount'],
            'currency' => $row['currency'],
            'pending' => (bool) $row['pending'],
        ], array_slice($new, 0, $limit));
    }

    public function newTotal(): float
    {
        return round(array_sum(array_map(
            fn (array $row): float => $row['pending'] ? 0.0 : (float) $row['amount'],
            array_diff_key($this->records, $this->existingUids),
        )), 2);
    }
}
