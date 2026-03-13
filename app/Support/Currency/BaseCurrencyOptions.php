<?php

namespace App\Support\Currency;

class BaseCurrencyOptions
{
    private const TOP_WORLD_CURRENCIES = [
        'EUR',
        'USD',
        'GBP',
        'JPY',
        'CHF',
        'CAD',
        'AUD',
        'NZD',
        'SEK',
        'NOK',
        'DKK',
        'PLN',
        'CZK',
        'HUF',
        'RON',
        'BGN',
        'TRY',
        'CNY',
        'HKD',
        'SGD',
        'INR',
        'AED',
        'SAR',
        'ZAR',
        'BRL',
        'MXN',
    ];

    private const CURRENCY_LABELS = [
        'EUR' => 'Euro',
        'USD' => 'US Dollar',
        'GBP' => 'British Pound',
        'JPY' => 'Japanese Yen',
        'CHF' => 'Swiss Franc',
        'CAD' => 'Canadian Dollar',
        'AUD' => 'Australian Dollar',
        'NZD' => 'New Zealand Dollar',
        'SEK' => 'Swedish Krona',
        'NOK' => 'Norwegian Krone',
        'DKK' => 'Danish Krone',
        'PLN' => 'Polish Zloty',
        'CZK' => 'Czech Koruna',
        'HUF' => 'Hungarian Forint',
        'RON' => 'Romanian Leu',
        'BGN' => 'Bulgarian Lev',
        'TRY' => 'Turkish Lira',
        'CNY' => 'Chinese Yuan',
        'HKD' => 'Hong Kong Dollar',
        'SGD' => 'Singapore Dollar',
        'INR' => 'Indian Rupee',
        'AED' => 'UAE Dirham',
        'SAR' => 'Saudi Riyal',
        'ZAR' => 'South African Rand',
        'BRL' => 'Brazilian Real',
        'MXN' => 'Mexican Peso',
    ];

    /**
     * @param  array<int, string>  $additionalCurrencies
     * @return array<int, string>
     */
    public static function all(array $additionalCurrencies = []): array
    {
        return collect(self::TOP_WORLD_CURRENCIES)
            ->merge($additionalCurrencies)
            ->map(fn (mixed $currency): string => strtoupper((string) $currency))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $additionalCurrencies
     * @return array<int, array{code: string, label: string}>
     */
    public static function options(array $additionalCurrencies = []): array
    {
        return collect(self::all($additionalCurrencies))
            ->map(fn (string $code): array => [
                'code' => $code,
                'label' => sprintf('%s - %s', $code, self::labelFor($code)),
            ])
            ->values()
            ->all();
    }

    private static function labelFor(string $code): string
    {
        return self::CURRENCY_LABELS[$code] ?? $code;
    }
}
