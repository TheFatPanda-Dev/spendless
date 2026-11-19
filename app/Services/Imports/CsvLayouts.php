<?php

namespace App\Services\Imports;

/**
 * Known CSV/Excel layouts (detected by their header row) and the generic column mapping
 * for everything else.
 */
final class CsvLayouts
{
    /**
     * @var array<string, array{label: string, bank: string, required: list<string>}>
     */
    private const LAYOUTS = [
        'revolut' => [
            'label' => 'Revolut statement',
            'bank' => 'Revolut',
            'required' => ['type', 'started date', 'description', 'amount', 'currency', 'state'],
        ],
        'barclays' => [
            'label' => 'Barclays CSV',
            'bank' => 'Barclays',
            'required' => ['date', 'account', 'amount', 'subcategory', 'memo'],
        ],
        'nationwide' => [
            'label' => 'Nationwide current account CSV',
            'bank' => 'Nationwide',
            'required' => ['date', 'transaction type', 'description', 'paid out', 'paid in'],
        ],
        'nationwide_card' => [
            'label' => 'Nationwide credit card CSV',
            'bank' => 'Nationwide',
            'required' => ['date', 'transactions', 'paid out', 'paid in'],
        ],
    ];

    private const REVOLUT_CANCELLED = ['REVERTED', 'DECLINED', 'FAILED', 'CANCELLED'];

    /**
     * @param  list<list<string>>  $rows
     * @param  array<string, mixed>|null  $mapping
     */
    public static function parse(array $rows, ?array $mapping = null): ParsedStatement
    {
        $preamble = [];

        foreach (self::LAYOUTS as $key => $layout) {
            $headerIndex = self::findHeaderRow($rows, $layout['required']);

            if ($headerIndex === null) {
                continue;
            }

            $preamble = array_slice($rows, 0, $headerIndex);
            $header = array_map([self::class, 'normalize'], $rows[$headerIndex]);
            $dataRows = array_slice($rows, $headerIndex + 1);

            return self::parseKnown($key, $layout, $header, $dataRows, $preamble);
        }

        return self::parseMapped($rows, $mapping);
    }

    /**
     * Default form values for a file we don't recognise, guessed from its header.
     *
     * @param  list<list<string>>  $rows
     * @return array{header_row: int, date: int|null, date_format: string, description: list<int>, amount: int|null, paid_out: int|null, paid_in: int|null, balance: int|null, reference: int|null, flip_sign: bool}
     */
    public static function guessMapping(array $rows): array
    {
        $headerRow = 0;

        foreach (array_slice($rows, 0, 25, true) as $index => $row) {
            $next = $rows[$index + 1] ?? null;

            if ($next === null || count(array_filter($row, fn (string $cell): bool => $cell !== '')) < 2) {
                continue;
            }

            $rowHasDate = array_filter($row, fn (string $cell): bool => ImportValueParser::date($cell) !== null) !== [];
            $nextHasDate = array_filter($next, fn (string $cell): bool => ImportValueParser::date($cell) !== null) !== [];

            if (! $rowHasDate && $nextHasDate) {
                $headerRow = $index;

                break;
            }
        }

        $header = array_map([self::class, 'normalize'], $rows[$headerRow] ?? []);
        $taken = [];
        // Whole-word matches only ("in" must not match "posting"), never reusing a column.
        $find = function (array $needles) use ($header, &$taken): ?int {
            foreach ($needles as $needle) {
                foreach ($header as $index => $cell) {
                    if (! in_array($index, $taken, true) && preg_match('/\b'.preg_quote($needle, '/').'\b/u', $cell) === 1) {
                        $taken[] = $index;

                        return $index;
                    }
                }
            }

            return null;
        };

        $date = $find(['date', 'posted', 'booked']);
        $balance = $find(['balance']);
        $paidOut = $find(['paid out', 'money out', 'debit', 'withdrawal', 'withdrawals', 'out']);
        $paidIn = $find(['paid in', 'money in', 'credit', 'deposit', 'deposits', 'in']);
        $amount = $find(['amount', 'value', 'sum']);
        $reference = $find(['transaction id', 'reference number', 'ref']);
        $description = $find(['description', 'details', 'narrative', 'memo', 'merchant', 'payee', 'transaction', 'reference', 'name']);

        return [
            'header_row' => $headerRow,
            'date' => $date,
            'date_format' => 'auto',
            'description' => $description !== null ? [$description] : [],
            'amount' => $paidOut !== null && $paidIn !== null ? null : $amount,
            'paid_out' => $amount === null || ($paidOut !== null && $paidIn !== null) ? $paidOut : null,
            'paid_in' => $amount === null || ($paidOut !== null && $paidIn !== null) ? $paidIn : null,
            'balance' => $balance,
            'reference' => $reference,
            'flip_sign' => false,
        ];
    }

    public static function normalize(string $cell): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strtolower(trim($cell, " \t\"':\u{00A0}"))));
    }

    /**
     * @param  list<list<string>>  $rows
     * @param  list<string>  $required
     */
    private static function findHeaderRow(array $rows, array $required): ?int
    {
        foreach (array_slice($rows, 0, 25, true) as $index => $row) {
            $cells = array_map([self::class, 'normalize'], $row);

            if (array_diff($required, $cells) === []) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array{label: string, bank: string, required: list<string>}  $layout
     * @param  list<string>  $header
     * @param  list<list<string>>  $dataRows
     * @param  list<list<string>>  $preamble
     */
    private static function parseKnown(string $key, array $layout, array $header, array $dataRows, array $preamble): ParsedStatement
    {
        $transactions = [];
        $warnings = [];
        $currencies = [];
        $products = [];

        foreach ($dataRows as $cells) {
            $row = [];

            foreach ($header as $index => $name) {
                $row[$name] = $cells[$index] ?? '';
            }

            $transaction = match ($key) {
                'revolut' => self::revolut($row),
                'barclays' => self::barclays($row),
                'nationwide' => self::nationwide($row),
                'nationwide_card' => self::nationwideCard($row),
            };

            if ($transaction === null) {
                continue;
            }

            if ($key === 'revolut') {
                $products[$row['product'] ?? ''] = ($products[$row['product'] ?? ''] ?? 0) + 1;
            }

            if ($transaction->currency !== null) {
                $currencies[$transaction->currency] = ($currencies[$transaction->currency] ?? 0) + 1;
            }

            $transactions[] = ['transaction' => $transaction, 'product' => $row['product'] ?? ''];
        }

        // A Revolut export can mix the main account with savings "products": keep the main one.
        if (count($products) > 1) {
            arsort($products);
            $mainProduct = (string) array_key_first($products);
            $transactions = array_values(array_filter($transactions, fn (array $item): bool => $item['product'] === $mainProduct));
            $warnings[] = sprintf('The file contains several Revolut products; only "%s" is imported.', $mainProduct !== '' ? $mainProduct : 'main account');
        }

        arsort($currencies);
        $currency = $currencies === [] ? null : (string) array_key_first($currencies);

        if (count($currencies) > 1) {
            $warnings[] = sprintf('The file contains several currencies (%s). Only the account\'s currency is imported.', implode(', ', array_keys($currencies)));
        }

        return (new ParsedStatement(
            format: $key,
            formatLabel: $layout['label'],
            bank: $layout['bank'],
            transactions: array_column($transactions, 'transaction'),
            accountHint: self::accountHint($preamble),
            currency: $currency ?? 'GBP',
            warnings: $warnings,
        ))->withBalanceFromRunningTotals();
    }

    /**
     * "CARD_PAYMENT" or "Card Payment" => "Card payment"; "TOPUP" => "Top-up".
     */
    private static function revolutType(string $type): ?string
    {
        $words = strtolower(trim(str_replace('_', ' ', $type)));

        if ($words === '') {
            return null;
        }

        return match ($words) {
            'topup', 'top up' => 'Top-up',
            'atm' => 'Cash withdrawal',
            default => ucfirst($words),
        };
    }

    /**
     * @param  array<string, string>  $row
     */
    private static function revolut(array $row): ?ParsedTransaction
    {
        $started = ImportValueParser::date($row['started date'] ?? '');
        $amount = ImportValueParser::amount($row['amount'] ?? '');

        if ($started === null || $amount === null) {
            return null;
        }

        // The fee is charged on top of the amount.
        $fee = ImportValueParser::amount($row['fee'] ?? '') ?? 0.0;
        $state = strtoupper(trim($row['state'] ?? ''));
        $description = trim($row['description'] ?? '') ?: 'Revolut transaction';

        return new ParsedTransaction(
            date: $started['date'],
            amount: round($amount - abs($fee), 2),
            // Revolut's one text column is the merchant; its type ("Card payment",
            // "Transfer") is the description, so the two say different things.
            description: self::revolutType($row['type'] ?? '') ?? $description,
            payee: $description,
            balanceAfter: ImportValueParser::amount($row['balance'] ?? ''),
            pending: $state === 'PENDING',
            cancelled: in_array($state, self::REVOLUT_CANCELLED, true),
            currency: strtoupper(trim($row['currency'] ?? '')) ?: null,
            sortKey: $started['datetime'],
            // Revolut has no reference; the start time + description survive the
            // pending -> completed change even when an FX amount settles differently.
            fingerprint: implode('|', ['revolut', $started['datetime'], $description, $row['type'] ?? '', $row['product'] ?? '']),
        );
    }

    /**
     * @param  array<string, string>  $row
     */
    private static function barclays(array $row): ?ParsedTransaction
    {
        $date = ImportValueParser::date($row['date'] ?? '', 'd/m/Y');
        $amount = ImportValueParser::amount($row['amount'] ?? '');

        if ($date === null || $amount === null) {
            return null;
        }

        $memo = trim(preg_replace('/\s+/', ' ', $row['memo'] ?? '') ?? '');

        return new ParsedTransaction(
            date: $date['date'],
            amount: $amount,
            description: $memo !== '' ? $memo : (trim($row['subcategory'] ?? '') ?: 'Barclays transaction'),
            sortKey: $date['datetime'],
        );
    }

    /**
     * @param  array<string, string>  $row
     */
    private static function nationwide(array $row): ?ParsedTransaction
    {
        $date = ImportValueParser::date($row['date'] ?? '');
        $out = ImportValueParser::amount($row['paid out'] ?? '') ?? 0.0;
        $in = ImportValueParser::amount($row['paid in'] ?? '') ?? 0.0;

        if ($date === null || ($out === 0.0 && $in === 0.0 && trim(($row['paid out'] ?? '').($row['paid in'] ?? '')) === '')) {
            return null;
        }

        $description = trim(implode(' ', array_filter([trim($row['description'] ?? '')])));

        return new ParsedTransaction(
            date: $date['date'],
            amount: round(abs($in) - abs($out), 2),
            description: $description !== '' ? $description : (trim($row['transaction type'] ?? '') ?: 'Nationwide transaction'),
            balanceAfter: ImportValueParser::amount($row['balance'] ?? ''),
            sortKey: $date['datetime'],
        );
    }

    /**
     * @param  array<string, string>  $row
     */
    private static function nationwideCard(array $row): ?ParsedTransaction
    {
        $date = ImportValueParser::date($row['date'] ?? '');
        $out = ImportValueParser::amount($row['paid out'] ?? '') ?? 0.0;
        $in = ImportValueParser::amount($row['paid in'] ?? '') ?? 0.0;

        if ($date === null || ($out === 0.0 && $in === 0.0)) {
            return null;
        }

        $description = trim(implode(' · ', array_filter([trim($row['transactions'] ?? ''), trim($row['location'] ?? '')])));

        return new ParsedTransaction(
            date: $date['date'],
            amount: round(abs($in) - abs($out), 2),
            description: $description !== '' ? $description : 'Card transaction',
            sortKey: $date['datetime'],
        );
    }

    /**
     * @param  list<list<string>>  $rows
     * @param  array<string, mixed>|null  $mapping
     */
    private static function parseMapped(array $rows, ?array $mapping): ParsedStatement
    {
        $guess = self::guessMapping($rows);
        $mapping = $mapping !== null ? [...$guess, ...$mapping] : $guess;
        $headerRow = max(0, (int) $mapping['header_row']);
        $columns = $rows[$headerRow] ?? [];
        $dataRows = array_slice($rows, $headerRow + 1);
        $hasAmount = $mapping['amount'] !== null || $mapping['paid_out'] !== null || $mapping['paid_in'] !== null;
        $complete = $mapping['date'] !== null && $hasAmount && ($mapping['description'] ?? []) !== [];

        $transactions = [];

        if ($complete) {
            $dateColumn = (int) $mapping['date'];
            $format = (string) ($mapping['date_format'] ?: 'auto');

            if ($format === 'auto') {
                $format = ImportValueParser::detectDateFormat(array_map(fn (array $row): string => $row[$dateColumn] ?? '', array_slice($dataRows, 0, 200))) ?? 'auto';
            }

            foreach ($dataRows as $row) {
                $date = ImportValueParser::date($row[$dateColumn] ?? '', $format);

                if ($date === null) {
                    continue;
                }

                if ($mapping['amount'] !== null) {
                    $amount = ImportValueParser::amount($row[(int) $mapping['amount']] ?? '');
                } else {
                    $out = $mapping['paid_out'] !== null ? ImportValueParser::amount($row[(int) $mapping['paid_out']] ?? '') : null;
                    $in = $mapping['paid_in'] !== null ? ImportValueParser::amount($row[(int) $mapping['paid_in']] ?? '') : null;
                    $amount = $out === null && $in === null ? null : round(abs($in ?? 0.0) - abs($out ?? 0.0), 2);
                }

                if ($amount === null) {
                    continue;
                }

                if ($mapping['flip_sign']) {
                    $amount = -$amount;
                }

                $description = trim(implode(' ', array_filter(array_map(
                    fn (int $column): string => trim($row[$column] ?? ''),
                    array_map('intval', (array) $mapping['description']),
                ))));

                $transactions[] = new ParsedTransaction(
                    date: $date['date'],
                    amount: $amount,
                    description: preg_replace('/\s+/', ' ', $description) ?: 'Transaction',
                    externalId: $mapping['reference'] !== null ? (trim($row[(int) $mapping['reference']] ?? '') ?: null) : null,
                    balanceAfter: $mapping['balance'] !== null ? ImportValueParser::amount($row[(int) $mapping['balance']] ?? '') : null,
                    sortKey: $date['datetime'],
                );
            }
        }

        return (new ParsedStatement(
            format: 'csv',
            formatLabel: 'CSV / spreadsheet',
            transactions: $transactions,
            accountHint: self::accountHint(array_slice($rows, 0, $headerRow)),
            needsMapping: ! $complete || $transactions === [],
            columns: array_values($columns),
            sampleRows: array_values(array_slice($dataRows, 0, 6)),
            mapping: $mapping,
            layoutSignature: hash('sha256', implode('|', array_map([self::class, 'normalize'], $columns))),
        ))->withBalanceFromRunningTotals();
    }

    /**
     * Lines above the header such as "Account Name:","FlexAccount ****1234".
     *
     * @param  list<list<string>>  $preamble
     */
    private static function accountHint(array $preamble): ?string
    {
        foreach ($preamble as $row) {
            $label = self::normalize($row[0] ?? '');

            if (in_array($label, ['account name', 'account', 'account number'], true) && trim($row[1] ?? '') !== '') {
                return trim($row[1]);
            }
        }

        return null;
    }
}
