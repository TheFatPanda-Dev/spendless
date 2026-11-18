<?php

namespace App\Services\Imports;

/**
 * One row of a bank statement, normalised. Amounts are signed: money in +, money out −.
 */
final class ParsedTransaction
{
    public function __construct(
        public readonly string $date,
        public readonly float $amount,
        public readonly string $description,
        public readonly ?string $payee = null,
        public readonly ?string $externalId = null,
        public readonly ?float $balanceAfter = null,
        public readonly bool $pending = false,
        /** The bank cancelled it (e.g. Revolut REVERTED): remove it if it was imported before. */
        public readonly bool $cancelled = false,
        public readonly ?string $currency = null,
        /** Date and time when known, used to order rows and to pick the latest balance. */
        public readonly ?string $sortKey = null,
        /** Stable identity when the bank gives no reference; defaults to date + amount + text. */
        public readonly ?string $fingerprint = null,
    ) {}
}
