<?php

namespace App\Services\Plaid;

use App\Models\AppSetting;

/**
 * Counts the /transactions/sync calls SpendLess makes, so it can stop before Plaid's free
 * allowance (200 calls per product) runs out. The Plaid Dashboard has the official figure.
 */
class PlaidUsage
{
    private const KEY = 'plaid_transactions_calls';

    /** Calls kept back for the user's own "Sync now" / reconnect when the limit is near. */
    private const RESERVE = 5;

    public static function used(): int
    {
        return (int) AppSetting::getValue(self::KEY, '0');
    }

    public static function limit(): int
    {
        return max(0, (int) config('services.plaid.call_limit', 200));
    }

    public static function record(int $calls = 1): void
    {
        AppSetting::putValue(self::KEY, (string) (self::used() + $calls));
    }

    /**
     * Background syncs stop RESERVE calls before the limit; a sync the user asked for
     * may use the reserve.
     */
    public static function exhausted(bool $userPresent = false): bool
    {
        $limit = self::limit();

        if ($limit === 0) {
            return false;
        }

        return self::used() >= ($userPresent ? $limit : $limit - self::RESERVE);
    }

    /**
     * @return array{used: int, limit: int}
     */
    public static function toArray(): array
    {
        return ['used' => self::used(), 'limit' => self::limit()];
    }
}
