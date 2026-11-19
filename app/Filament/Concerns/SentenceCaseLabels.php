<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Str;

/**
 * Page titles in sentence case ("Bank connections"), like the rest of SpendLess,
 * instead of Filament's Title Case.
 */
trait SentenceCaseLabels
{
    public static function getTitleCaseModelLabel(): string
    {
        return Str::ucfirst(static::getModelLabel());
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return Str::ucfirst(static::getPluralModelLabel());
    }
}
