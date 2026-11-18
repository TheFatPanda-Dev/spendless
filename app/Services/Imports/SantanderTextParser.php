<?php

namespace App\Services\Imports;

/**
 * Santander UK's "Text (.txt)" download: blocks of "Date: / Description: / Amount: / Balance:".
 */
final class SantanderTextParser
{
    public static function looksLikeSantanderText(string $text): bool
    {
        $text = str_replace("\u{00A0}", ' ', $text);

        return preg_match('/^\s*Date:\s*\d{2}\/\d{2}\/\d{4}/m', $text) === 1
            && preg_match('/^\s*Description:/m', $text) === 1
            && preg_match('/^\s*Amount:/m', $text) === 1;
    }

    public static function parse(string $text): ParsedStatement
    {
        $text = str_replace("\u{00A0}", ' ', $text);
        $transactions = [];
        $currency = null;

        foreach (preg_split('/\n\s*\n/', $text) ?: [] as $block) {
            $fields = [];

            foreach (explode("\n", $block) as $line) {
                if (preg_match('/^\s*([A-Za-z ]+):\s*(.*)$/', $line, $match) === 1) {
                    $fields[strtolower(trim($match[1]))] = trim($match[2]);
                }
            }

            if (! isset($fields['date'], $fields['amount'])) {
                continue;
            }

            $date = ImportValueParser::date($fields['date'], 'd/m/Y');
            $amount = ImportValueParser::amount($fields['amount']);

            if ($date === null || $amount === null) {
                continue;
            }

            if (preg_match('/\b([A-Z]{3})\s*$/', $fields['amount'], $currencyMatch) === 1) {
                $currency ??= $currencyMatch[1];
            }

            $transactions[] = new ParsedTransaction(
                date: $date['date'],
                amount: $amount,
                description: trim(preg_replace('/\s+/', ' ', $fields['description'] ?? '') ?? '') ?: 'Transaction',
                balanceAfter: isset($fields['balance']) ? ImportValueParser::amount($fields['balance']) : null,
                currency: $currency,
                sortKey: $date['datetime'],
            );
        }

        preg_match('/Account:\s*([^\n]+)/', $text, $accountMatch);
        $account = isset($accountMatch[1]) ? trim($accountMatch[1]) : null;

        return (new ParsedStatement(
            format: 'santander_txt',
            formatLabel: 'Santander text download',
            bank: 'Santander',
            transactions: $transactions,
            accountHint: $account !== null && $account !== '' ? 'Account ending '.substr(preg_replace('/\D/', '', $account) ?: $account, -4) : null,
            currency: $currency ?? 'GBP',
        ))->withBalanceFromRunningTotals();
    }
}
