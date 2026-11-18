<?php

namespace App\Support\Text;

use App\Models\BankTransaction;
use Illuminate\Support\Str;

/**
 * Lower-case text with punctuation turned into spaces, so "TESCO,LONDON", "(interest)"
 * and "J. Novak" compare cleanly against keywords and rules.
 */
final class SearchText
{
    public static function normalize(?string $value): string
    {
        return (string) Str::of((string) $value)
            ->replaceMatches('/[^\p{L}\p{N}\s]+/u', ' ')
            ->lower()
            ->squish();
    }

    /**
     * Everything that describes who was paid and what for.
     */
    public static function forTransaction(BankTransaction $transaction): string
    {
        return collect([
            $transaction->merchant_name,
            $transaction->payee,
            $transaction->name,
            $transaction->description,
        ])
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => self::normalize($value))
            ->implode(' ');
    }
}
