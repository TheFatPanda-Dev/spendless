<?php

namespace App\Services\Imports;

/**
 * OFX/QFX ("Microsoft Money" / "Quicken" downloads), both the SGML (1.x) and XML (2.x)
 * variants. Every transaction carries the bank's FITID, which makes re-imports exact.
 */
final class OfxParser
{
    public static function looksLikeOfx(string $text): bool
    {
        return stripos($text, 'OFXHEADER') !== false || stripos($text, '<OFX>') !== false;
    }

    public static function parse(string $text): ParsedStatement
    {
        $isCreditCard = stripos($text, '<CCSTMTRS>') !== false || stripos($text, '<CCACCTFROM>') !== false;
        $currency = self::leaf($text, 'CURDEF');
        $accountId = self::leaf($text, 'ACCTID');
        $transactions = [];

        preg_match_all('/<STMTTRN>(.*?)(?=<\/STMTTRN>|<STMTTRN>|<\/BANKTRANLIST>)/is', $text, $matches);

        foreach ($matches[1] as $block) {
            $date = ImportValueParser::date(substr((string) self::leaf($block, 'DTPOSTED'), 0, 8), 'Ymd');
            $amount = ImportValueParser::amount((string) self::leaf($block, 'TRNAMT'));

            if ($date === null || $amount === null) {
                continue;
            }

            $name = self::clean(self::leaf($block, 'NAME') ?? self::leaf($block, 'PAYEE'));
            $memo = self::clean(self::leaf($block, 'MEMO'));
            $description = trim(implode(' ', array_unique(array_filter([$name, $memo]))));

            $transactions[] = new ParsedTransaction(
                date: $date['date'],
                amount: $amount,
                description: $description !== '' ? $description : (string) (self::leaf($block, 'TRNTYPE') ?? 'Transaction'),
                payee: $name,
                externalId: self::clean(self::leaf($block, 'FITID')),
                currency: $currency !== null ? strtoupper($currency) : null,
                sortKey: $date['datetime'],
            );
        }

        $ledger = self::balance($text, 'LEDGERBAL');
        $available = self::balance($text, 'AVAILBAL');

        return new ParsedStatement(
            format: 'ofx',
            formatLabel: 'OFX statement',
            transactions: $transactions,
            accountHint: $accountId !== null ? ($isCreditCard ? 'Card' : 'Account').' ending '.substr($accountId, -4) : null,
            currency: $currency !== null ? strtoupper($currency) : null,
            closingBalance: $ledger['amount'] ?? null,
            availableBalance: $available['amount'] ?? null,
            balanceDate: $ledger['date'] ?? null,
        );
    }

    /**
     * @return array{amount: float, date: string|null}|null
     */
    private static function balance(string $text, string $aggregate): ?array
    {
        if (preg_match('/<'.$aggregate.'>(.*?)(?:<\/'.$aggregate.'>|<\/STMTRS>|<\/CCSTMTRS>|$)/is', $text, $match) !== 1) {
            return null;
        }

        $amount = ImportValueParser::amount((string) self::leaf($match[1], 'BALAMT'));

        if ($amount === null) {
            return null;
        }

        $date = ImportValueParser::date(substr((string) self::leaf($match[1], 'DTASOF'), 0, 8), 'Ymd');

        return ['amount' => $amount, 'date' => $date['date'] ?? null];
    }

    private static function leaf(string $text, string $tag): ?string
    {
        if (preg_match('/<'.$tag.'>([^<\r\n]*)/i', $text, $match) !== 1) {
            return null;
        }

        $value = trim(html_entity_decode($match[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));

        return $value === '' ? null : $value;
    }

    private static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return $value === '' ? null : $value;
    }
}
