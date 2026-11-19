<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Initials drawn locally as an SVG. Filament's default sends the user's name to
 * ui-avatars.com; this keeps it on the server.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $name = Filament::getNameForDefaultAvatar($record);
        $initials = Str::of($name)
            ->trim()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#ddf4e6"/>'
            .'<text x="32" y="41" font-family="Roboto, sans-serif" font-size="26" font-weight="500" fill="#006447" text-anchor="middle">%s</text></svg>',
            e($initials !== '' ? $initials : '?'),
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
