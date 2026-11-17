<?php

namespace App\Services\EnableBanking;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Turns Enable Banking `Transaction` / `Balance` objects into SpendLess rows.
 *
 * Amounts are stored signed: positive = money in, negative = money out. Enable Banking
 * sends the direction separately in `credit_debit_indicator` (CRDT / DBIT).
 */
final class EnableBankingTransactionMapper
{
    private const SKIPPED_STATUSES = ['CNCL', 'RJCT'];

    /** @var array<string, int> */
    private array $fallbackUidOccurrences = [];

    /**
     * @param  array<string, mixed>  $transaction
     * @return array{external_uid: string, name: string, merchant_name: string|null, description: string|null, amount: float, currency: string, date: string, pending: bool}|null
     */
    public function map(array $transaction, string $fallbackCurrency): ?array
    {
        $status = strtoupper((string) Arr::get($transaction, 'status', 'BOOK'));

        if (in_array($status, self::SKIPPED_STATUSES, true)) {
            return null;
        }

        $rawAmount = Arr::get($transaction, 'transaction_amount.amount');

        if (! is_numeric($rawAmount)) {
            return null;
        }

        $indicator = strtoupper((string) Arr::get($transaction, 'credit_debit_indicator', ''));
        $amount = abs((float) $rawAmount);

        if ($indicator === 'DBIT' || ($indicator !== 'CRDT' && (float) $rawAmount < 0)) {
            $amount = -$amount;
        }

        $amount = round($amount, 2);
        $date = $this->firstDate($transaction) ?? now()->toDateString();
        $counterparty = $this->text(Arr::get($transaction, $amount < 0 ? 'creditor.name' : 'debtor.name'));
        $remittance = $this->remittance($transaction);
        $name = $remittance
            ?? $counterparty
            ?? $this->text(Arr::get($transaction, 'bank_transaction_code.description'))
            ?? ($amount < 0 ? 'Payment' : 'Incoming payment');

        return [
            'external_uid' => $this->externalUid($transaction, $date, $amount, $indicator, $counterparty, $remittance),
            'name' => Str::limit($name, 250, ''),
            'merchant_name' => $counterparty !== null ? Str::limit($counterparty, 250, '') : null,
            'description' => $remittance,
            'amount' => $amount,
            'currency' => strtoupper((string) (Arr::get($transaction, 'transaction_amount.currency') ?: $fallbackCurrency)),
            'date' => $date,
            'pending' => $status === 'PDNG',
        ];
    }

    /**
     * Picks a "current" and an "available" balance from the bank's balance list.
     *
     * @param  list<array<string, mixed>>  $balances
     * @return array{current: float, available: float|null}|null
     */
    public static function pickBalances(array $balances, ?string $currency): ?array
    {
        $byType = [];

        foreach ($balances as $balance) {
            $amount = Arr::get($balance, 'balance_amount.amount');
            $balanceCurrency = Arr::get($balance, 'balance_amount.currency');

            if (! is_numeric($amount)) {
                continue;
            }

            if ($currency !== null && is_string($balanceCurrency) && strtoupper($balanceCurrency) !== strtoupper($currency)) {
                continue;
            }

            $byType[strtoupper((string) Arr::get($balance, 'balance_type', 'OTHR'))] ??= round((float) $amount, 2);
        }

        if ($byType === []) {
            return null;
        }

        $pick = function (array $preferred) use ($byType): ?float {
            foreach ($preferred as $type) {
                if (array_key_exists($type, $byType)) {
                    return $byType[$type];
                }
            }

            return null;
        };

        $current = $pick(['ITBD', 'CLBD', 'XPCD', 'ITAV', 'CLAV', 'VALU', 'OPBD', 'OTHR', 'INFO']) ?? reset($byType);

        return [
            'current' => $current,
            'available' => $pick(['ITAV', 'CLAV', 'XPCD', 'FWAV', 'OPAV']),
        ];
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function firstDate(array $transaction): ?string
    {
        foreach (['booking_date', 'value_date', 'transaction_date'] as $field) {
            $value = Arr::get($transaction, $field);

            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
                return substr($value, 0, 10);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function remittance(array $transaction): ?string
    {
        $parts = Arr::wrap(Arr::get($transaction, 'remittance_information', []));

        $text = collect($parts)
            ->filter(fn (mixed $part): bool => is_string($part))
            ->map(fn (string $part): string => trim($part))
            ->filter()
            ->implode(' ');

        return $this->text($text);
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = Str::squish($value);

        return $value === '' ? null : $value;
    }

    /**
     * Banks should send a stable `entry_reference`. When they don't, fall back to a hash of
     * the transaction's content; identical transactions on the same day (two coffees) get
     * an occurrence suffix so they aren't merged into one.
     *
     * @param  array<string, mixed>  $transaction
     */
    private function externalUid(array $transaction, string $date, float $amount, string $indicator, ?string $counterparty, ?string $remittance): string
    {
        foreach (['entry_reference', 'transaction_id'] as $field) {
            $value = $this->text(Arr::get($transaction, $field));

            if ($value !== null) {
                return Str::limit($value, 250, '');
            }
        }

        $hash = hash('sha256', json_encode([
            $date,
            number_format($amount, 2, '.', ''),
            $indicator,
            $counterparty,
            $remittance,
            Arr::get($transaction, 'reference_number'),
        ], JSON_THROW_ON_ERROR));

        $occurrence = $this->fallbackUidOccurrences[$hash] = ($this->fallbackUidOccurrences[$hash] ?? 0) + 1;

        return 'h:'.$hash.':'.$occurrence;
    }
}
