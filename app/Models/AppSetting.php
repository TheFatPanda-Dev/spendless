<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Small key/value store for settings the admin can change at runtime.
 */
class AppSetting extends Model
{
    public const REGISTRATION_OPEN = 'registration_open';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $value = Cache::rememberForever(
            'app-setting:'.$key,
            fn (): string => (string) (static::query()->find($key)?->value ?? "\0default"),
        );

        return $value === "\0default" ? $default : $value;
    }

    public static function putValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('app-setting:'.$key);
    }

    public static function registrationOpen(): bool
    {
        return static::getValue(self::REGISTRATION_OPEN, '1') === '1';
    }
}
