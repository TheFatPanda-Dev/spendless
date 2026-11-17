<?php

namespace App\Services\Imports;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Amount and date parsing that copes with the formats UK and EU banks export.
 */
final class ImportValueParser
{
    /** Formats tried, in order, when the date format is "auto". Day-first (UK) before month-first. */
    public const DATE_FORMATS = [
        'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d',
        'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'd/m/y',
        'd-m-Y', 'd.m.Y', 'd.m.Y H:i',
        'd M Y', 'j M Y', 'd-M-Y', 'd M y', 'd-M-y', 'd F Y', 'j F Y',
        'Ymd',
    ];

    /** Extra choices offered in the mapping form; never guessed automatically. */
    public const MANUAL_DATE_FORMATS = ['m/d/Y', 'm/d/y', 'Y/m/d'];

    /**
     * "£1,234.56", "-12.34", "12.34-", "(12.34)", "1.234,56 €", "12.34 CR", "12.34 DR" → float.
     */
    public static function amount(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }

        $text = trim(str_replace(["\u{00A0}", "\u{202F}", ' '], '', (string) $value));

        if ($text === '') {
            return null;
        }

        $upper = strtoupper($text);
        $negative = preg_match('/^[^\d]*-/', $text) === 1
            || str_ends_with($text, '-')
            || (str_starts_with($text, '(') && str_ends_with($text, ')'))
            || str_ends_with($upper, 'DR');

        $number = preg_replace('/[^\d,.]/', '', $text) ?? '';

        if ($number === '' || preg_match('/\d/', $number) !== 1) {
            return null;
        }

        $lastComma = strrpos($number, ',');
        $lastDot = strrpos($number, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $decimal = $lastComma > $lastDot ? ',' : '.';
        } elseif ($lastComma !== false) {
            // "12,50" is a decimal comma, "1,250" a thousands separator.
            $decimal = preg_match('/,\d{1,2}$/', $number) === 1 ? ',' : null;
        } else {
            $decimal = $lastDot !== false && preg_match('/\.\d{3}$/', $number) === 1 && substr_count($number, '.') > 1 ? null : '.';
        }

        $thousands = $decimal === ',' ? '.' : ',';
        $number = str_replace($thousands, '', $number);

        if ($decimal === ',') {
            $number = str_replace(',', '.', $number);
        } elseif ($decimal === null) {
            $number = str_replace(['.', ','], '', $number);
        }

        if (! is_numeric($number)) {
            return null;
        }

        $amount = round((float) $number, 2);

        return $negative ? -$amount : $amount;
    }

    /**
     * @return array{date: string, datetime: string}|null
     */
    public static function date(mixed $value, string $format = 'auto'): ?array
    {
        if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^\d{5}(\.\d+)?$/', trim($value)) === 1)) {
            return self::excelSerial((float) $value);
        }

        $text = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');

        if ($text === '') {
            return null;
        }

        $formats = $format === 'auto' ? self::DATE_FORMATS : [$format];

        foreach ($formats as $candidate) {
            $parsed = self::tryFormat($text, $candidate);

            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * The first format that reads every sample value, or null.
     *
     * @param  list<string>  $samples
     */
    public static function detectDateFormat(array $samples): ?string
    {
        $samples = array_values(array_filter(array_map('trim', $samples), fn (string $value): bool => $value !== ''));

        if ($samples === []) {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            $all = true;

            foreach ($samples as $sample) {
                if (self::tryFormat($sample, $format) === null) {
                    $all = false;

                    break;
                }
            }

            if ($all) {
                return $format;
            }
        }

        return null;
    }

    /**
     * @return array{date: string, datetime: string}|null
     */
    private static function tryFormat(string $text, string $format): ?array
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!'.$format, $text);
        } catch (Throwable) {
            return null;
        }

        if ($parsed === false || $parsed === null) {
            return null;
        }

        $errors = CarbonImmutable::getLastErrors();

        if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }

        // Reject nonsense years (e.g. "Ymd" matching a reference number).
        if ($parsed->year < 1990 || $parsed->year > (int) now()->year + 1) {
            return null;
        }

        return ['date' => $parsed->toDateString(), 'datetime' => $parsed->format('Y-m-d H:i:s')];
    }

    /**
     * @return array{date: string, datetime: string}|null
     */
    private static function excelSerial(float $serial): ?array
    {
        if ($serial < 20000 || $serial > 80000) {
            return null;
        }

        $parsed = CarbonImmutable::create(1899, 12, 30)->addSeconds((int) round($serial * 86400));

        return ['date' => $parsed->toDateString(), 'datetime' => $parsed->format('Y-m-d H:i:s')];
    }
}
