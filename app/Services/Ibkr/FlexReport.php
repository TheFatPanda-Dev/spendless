<?php

namespace App\Services\Ibkr;

/**
 * One IBKR account's data from a Flex report, normalised.
 */
final class FlexReport
{
    /**
     * @param  list<array<string, mixed>>  $positions
     * @param  list<array<string, mixed>>  $activities  Trades, deposits and withdrawals (not spending).
     * @param  list<array<string, mixed>>  $cashTransactions  Dividends, interest, fees, taxes (income/expenses).
     */
    public function __construct(
        public readonly string $accountId,
        public readonly string $baseCurrency,
        public readonly ?string $accountType,
        public readonly ?float $netAssetValue,
        public readonly ?float $cash,
        public readonly ?string $valueDate,
        public readonly array $positions,
        public readonly array $activities,
        public readonly array $cashTransactions,
    ) {}
}
