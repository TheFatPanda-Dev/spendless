<?php

namespace App\Services\Imports;

final class ParsedStatement
{
    /**
     * @param  list<ParsedTransaction>  $transactions
     * @param  list<string>  $warnings
     * @param  list<string>  $columns  Header cells, for the column-mapping form.
     * @param  list<list<string>>  $sampleRows  First data rows, for the column-mapping form.
     * @param  array<string, mixed>|null  $mapping  Column mapping used (generic CSV only).
     */
    public function __construct(
        public readonly string $format,
        public readonly string $formatLabel,
        public readonly ?string $bank = null,
        public readonly array $transactions = [],
        public readonly ?string $accountHint = null,
        public readonly ?string $currency = null,
        public readonly ?float $closingBalance = null,
        public readonly ?float $availableBalance = null,
        public readonly ?string $balanceDate = null,
        public readonly array $warnings = [],
        public readonly bool $needsMapping = false,
        public readonly array $columns = [],
        public readonly array $sampleRows = [],
        public readonly ?array $mapping = null,
        public readonly ?string $layoutSignature = null,
    ) {}

    public function withBalanceFromRunningTotals(): self
    {
        if ($this->closingBalance !== null) {
            return $this;
        }

        $withBalance = array_values(array_filter(
            $this->transactions,
            fn (ParsedTransaction $transaction): bool => $transaction->balanceAfter !== null && ! $transaction->pending,
        ));

        if ($withBalance === []) {
            return $this;
        }

        // Files are either oldest-first or newest-first; on equal dates the row that comes
        // last in reading order (oldest-first) or first (newest-first) is the latest.
        $first = $withBalance[0];
        $last = $withBalance[count($withBalance) - 1];
        $oldestFirst = ($first->sortKey ?? $first->date) <= ($last->sortKey ?? $last->date);
        $latest = $oldestFirst ? $last : $first;

        foreach ($withBalance as $transaction) {
            if (($transaction->sortKey ?? $transaction->date) > ($latest->sortKey ?? $latest->date)) {
                $latest = $transaction;
            }
        }

        return new self(
            format: $this->format,
            formatLabel: $this->formatLabel,
            bank: $this->bank,
            transactions: $this->transactions,
            accountHint: $this->accountHint,
            currency: $this->currency,
            closingBalance: $latest->balanceAfter,
            availableBalance: $this->availableBalance,
            balanceDate: $latest->date,
            warnings: $this->warnings,
            needsMapping: $this->needsMapping,
            columns: $this->columns,
            sampleRows: $this->sampleRows,
            mapping: $this->mapping,
            layoutSignature: $this->layoutSignature,
        );
    }
}
