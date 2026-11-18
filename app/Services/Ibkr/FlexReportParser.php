<?php

namespace App\Services\Ibkr;

use App\Services\Imports\ImportValueParser;
use SimpleXMLElement;

/**
 * Reads an Activity Flex Query report (XML).
 */
final class FlexReportParser
{
    /**
     * @return list<FlexReport>
     */
    public static function parse(string $xml): array
    {
        $root = @simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);

        if ($root === false) {
            throw new FlexException('The IBKR report could not be read.');
        }

        $reports = [];

        foreach ($root->xpath('//FlexStatement') ?: [] as $statement) {
            $accountId = self::text($statement, 'accountId');

            if ($accountId === null) {
                continue;
            }

            $info = $statement->AccountInformation;
            $baseCurrency = strtoupper(self::text($info, 'currency') ?? 'USD');
            $positions = self::positions($statement);
            [$nav, $cash, $valueDate] = self::netAssetValue($statement, $positions);
            [$activities, $cashTransactions] = self::cashTransactions($statement);

            $reports[] = new FlexReport(
                accountId: $accountId,
                baseCurrency: $baseCurrency,
                accountType: self::text($info, 'accountType'),
                netAssetValue: $nav,
                cash: $cash,
                valueDate: $valueDate ?? self::date(self::text($statement, 'toDate')),
                positions: $positions,
                activities: [...self::trades($statement), ...$activities],
                cashTransactions: $cashTransactions,
            );
        }

        return $reports;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function positions(SimpleXMLElement $statement): array
    {
        $positions = [];

        foreach ($statement->OpenPositions->OpenPosition ?? [] as $row) {
            $level = strtoupper(self::text($row, 'levelOfDetail') ?? 'SUMMARY');

            if ($level !== 'SUMMARY') {
                continue;
            }

            $value = self::number($row, 'positionValue') ?? 0.0;
            $fx = self::number($row, 'fxRateToBase') ?? 1.0;
            $unrealized = self::number($row, 'fifoPnlUnrealized');

            $positions[] = [
                'conid' => self::text($row, 'conid') ?? self::text($row, 'symbol') ?? uniqid('pos'),
                'symbol' => self::text($row, 'symbol') ?? '?',
                'description' => self::text($row, 'description'),
                'asset_category' => self::text($row, 'assetCategory'),
                'quantity' => self::number($row, 'position') ?? 0.0,
                'mark_price' => self::number($row, 'markPrice'),
                'position_value' => round($value, 2),
                'cost_basis' => self::number($row, 'costBasisMoney'),
                'unrealized_pnl' => $unrealized,
                'currency' => strtoupper(self::text($row, 'currency') ?? 'USD'),
                'fx_rate_to_base' => $fx,
                'value_in_base' => round($value * $fx, 2),
                'unrealized_pnl_in_base' => $unrealized !== null ? round($unrealized * $fx, 2) : null,
                'report_date' => self::date(self::text($row, 'reportDate')),
            ];
        }

        return $positions;
    }

    /**
     * Account value in the base currency: the latest equity summary, or (when that section
     * isn't in the query) holdings plus cash.
     *
     * @param  list<array<string, mixed>>  $positions
     * @return array{float|null, float|null, string|null}
     */
    private static function netAssetValue(SimpleXMLElement $statement, array $positions): array
    {
        $latest = null;

        foreach ($statement->EquitySummaryInBase->EquitySummaryByReportDateInBase ?? [] as $row) {
            $date = self::date(self::text($row, 'reportDate'));

            if ($date !== null && ($latest === null || $date >= $latest['date'])) {
                $latest = ['date' => $date, 'total' => self::number($row, 'total'), 'cash' => self::number($row, 'cash')];
            }
        }

        if ($latest !== null && $latest['total'] !== null) {
            return [$latest['total'], $latest['cash'], $latest['date']];
        }

        $cash = null;

        foreach ($statement->CashReport->CashReportCurrency ?? [] as $row) {
            if (strtoupper(self::text($row, 'currency') ?? '') === 'BASE_SUMMARY') {
                $cash = self::number($row, 'endingCash');
            }
        }

        if ($positions === [] && $cash === null) {
            return [null, null, null];
        }

        $holdings = array_sum(array_column($positions, 'value_in_base'));

        return [round($holdings + ($cash ?? 0.0), 2), $cash, $positions[0]['report_date'] ?? null];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function trades(SimpleXMLElement $statement): array
    {
        $trades = [];

        foreach ($statement->Trades->Trade ?? [] as $row) {
            $level = strtoupper(self::text($row, 'levelOfDetail') ?? 'EXECUTION');

            if ($level !== 'EXECUTION') {
                continue;
            }

            $id = self::text($row, 'tradeID') ?? self::text($row, 'transactionID');
            $date = self::date(self::text($row, 'tradeDate') ?? self::text($row, 'dateTime'));

            if ($id === null || $date === null) {
                continue;
            }

            $side = strtoupper(self::text($row, 'buySell') ?? '');

            $trades[] = [
                'external_id' => 'trade:'.$id,
                'kind' => str_starts_with($side, 'SELL') ? 'sell' : 'buy',
                'date' => $date,
                'symbol' => self::text($row, 'symbol'),
                'description' => self::text($row, 'description') ?? self::text($row, 'symbol') ?? 'Trade',
                'quantity' => self::number($row, 'quantity'),
                'price' => self::number($row, 'tradePrice'),
                'amount' => round(self::number($row, 'netCash') ?? self::number($row, 'proceeds') ?? 0.0, 2),
                'commission' => self::number($row, 'ibCommission'),
                'currency' => strtoupper(self::text($row, 'currency') ?? 'USD'),
                'fx_rate_to_base' => self::number($row, 'fxRateToBase') ?? 1.0,
            ];
        }

        return $trades;
    }

    /**
     * Deposits and withdrawals are moves of your own money, so they go with the trades;
     * everything else (dividends, interest, fees, taxes) counts as income or expense.
     *
     * @return array{list<array<string, mixed>>, list<array<string, mixed>>}
     */
    private static function cashTransactions(SimpleXMLElement $statement): array
    {
        $activities = [];
        $transactions = [];

        foreach ($statement->CashTransactions->CashTransaction ?? [] as $row) {
            $level = strtoupper(self::text($row, 'levelOfDetail') ?? 'DETAIL');

            if ($level !== 'DETAIL') {
                continue;
            }

            $amount = self::number($row, 'amount');
            $date = self::date(self::text($row, 'dateTime') ?? self::text($row, 'reportDate') ?? self::text($row, 'settleDate'));
            $type = self::text($row, 'type') ?? 'Cash transaction';
            $id = self::text($row, 'transactionID')
                ?? hash('sha256', implode('|', [$date, $type, $amount, self::text($row, 'description'), self::text($row, 'currency')]));

            if ($amount === null || $date === null || $amount == 0.0) {
                continue;
            }

            $currency = strtoupper(self::text($row, 'currency') ?? 'USD');
            $description = self::text($row, 'description') ?? $type;

            if (preg_match('/deposit|withdrawal/i', $type) === 1) {
                $activities[] = [
                    'external_id' => 'cash:'.$id,
                    'kind' => $amount > 0 ? 'deposit' : 'withdrawal',
                    'date' => $date,
                    'symbol' => null,
                    'description' => $description,
                    'quantity' => null,
                    'price' => null,
                    'amount' => round($amount, 2),
                    'commission' => null,
                    'currency' => $currency,
                    'fx_rate_to_base' => self::number($row, 'fxRateToBase') ?? 1.0,
                ];

                continue;
            }

            $symbol = self::text($row, 'symbol');

            $transactions[] = [
                'external_uid' => 'ibkr:'.$id,
                'date' => $date,
                'amount' => round($amount, 2),
                'currency' => $currency,
                // "Dividends: AAPL ..." lets the category rules recognise dividends and interest.
                'name' => trim($type.': '.($symbol !== null && ! str_contains($description, $symbol) ? $symbol.' ' : '').$description),
                'merchant_name' => $symbol,
                'description' => $description,
            ];
        }

        return [$activities, $transactions];
    }

    private static function text(?SimpleXMLElement $node, string $attribute): ?string
    {
        if ($node === null || ! isset($node[$attribute])) {
            return null;
        }

        $value = trim((string) $node[$attribute]);

        return $value === '' || $value === '--' ? null : $value;
    }

    private static function number(SimpleXMLElement $node, string $attribute): ?float
    {
        $value = self::text($node, $attribute);

        return $value !== null && is_numeric($value) ? (float) $value : null;
    }

    /**
     * IBKR's default "20261017" or "20261017;101500", plus the other formats a query can be set to.
     */
    private static function date(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/^(\d{8})(?:[;, T]|$)/', $value, $match) === 1) {
            return ImportValueParser::date($match[1], 'Ymd')['date'] ?? null;
        }

        $datePart = preg_split('/[;,]\s*/', $value)[0] ?? $value;

        return ImportValueParser::date($value)['date'] ?? ImportValueParser::date($datePart)['date'] ?? null;
    }
}
