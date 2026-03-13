<?php

namespace App\Support\Localization;

class NumberLocaleOptions
{
    private const TOP_LOCALES = [
        'en-GB',
        'en-US',
        'de-DE',
        'fr-FR',
        'es-ES',
        'it-IT',
        'nl-NL',
        'pt-PT',
        'pt-BR',
        'pl-PL',
        'cs-CZ',
        'hu-HU',
        'ro-RO',
        'sv-SE',
        'da-DK',
        'tr-TR',
        'ja-JP',
        'ko-KR',
        'zh-CN',
        'hi-IN',
        'ar-EG',
    ];

    private const LABELS = [
        'en-GB' => 'English (UK)',
        'en-US' => 'English (US)',
        'de-DE' => 'German (Germany)',
        'fr-FR' => 'French (France)',
        'es-ES' => 'Spanish (Spain)',
        'it-IT' => 'Italian (Italy)',
        'nl-NL' => 'Dutch (Netherlands)',
        'pt-PT' => 'Portuguese (Portugal)',
        'pt-BR' => 'Portuguese (Brazil)',
        'pl-PL' => 'Polish (Poland)',
        'cs-CZ' => 'Czech (Czechia)',
        'hu-HU' => 'Hungarian (Hungary)',
        'ro-RO' => 'Romanian (Romania)',
        'sv-SE' => 'Swedish (Sweden)',
        'da-DK' => 'Danish (Denmark)',
        'tr-TR' => 'Turkish (Turkey)',
        'ja-JP' => 'Japanese (Japan)',
        'ko-KR' => 'Korean (South Korea)',
        'zh-CN' => 'Chinese (Simplified, China)',
        'hi-IN' => 'Hindi (India)',
        'ar-EG' => 'Arabic (Egypt)',
    ];

    private const EXAMPLES = [
        'en-GB' => '1,000.00',
        'en-US' => '1,000.00',
        'de-DE' => '1.000,00',
        'fr-FR' => '1 000,00',
        'es-ES' => '1.000,00',
        'it-IT' => '1.000,00',
        'nl-NL' => '1.000,00',
        'pt-PT' => '1 000,00',
        'pt-BR' => '1.000,00',
        'pl-PL' => '1 000,00',
        'cs-CZ' => '1 000,00',
        'hu-HU' => '1 000,00',
        'ro-RO' => '1.000,00',
        'sv-SE' => '1 000,00',
        'da-DK' => '1.000,00',
        'tr-TR' => '1.000,00',
        'ja-JP' => '1,000.00',
        'ko-KR' => '1,000.00',
        'zh-CN' => '1,000.00',
        'hi-IN' => '1,000.00',
        'ar-EG' => '١٬٠٠٠٫٠٠',
    ];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return self::TOP_LOCALES;
    }

    /**
     * @return array<int, array{code: string, label: string, example: string}>
     */
    public static function options(): array
    {
        return collect(self::TOP_LOCALES)
            ->map(fn (string $locale): array => [
                'code' => $locale,
                'label' => sprintf('%s - %s', $locale, self::LABELS[$locale] ?? $locale),
                'example' => self::EXAMPLES[$locale] ?? '1,000.00',
            ])
            ->values()
            ->all();
    }

    public static function normalize(string $locale): string
    {
        $normalized = trim($locale);

        return in_array($normalized, self::TOP_LOCALES, true) ? $normalized : 'en-GB';
    }
}
