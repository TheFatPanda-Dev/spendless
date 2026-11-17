<?php

namespace App\Services\EnableBanking;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Background syncs run in fixed slots counted from midnight (every 6 h = 00, 06, 12, 18),
 * so each connection is refreshed at most 4 times a day without the user present.
 */
final class SyncSchedule
{
    public static function intervalHours(): int
    {
        return max(1, min(24, (int) config('services.enable_banking.sync_interval_hours', 6)));
    }

    public static function nextSlot(?CarbonInterface $from = null): CarbonImmutable
    {
        $from = CarbonImmutable::instance($from ?? now());
        $interval = self::intervalHours();
        $slot = $from->startOfDay();

        while ($slot->lessThanOrEqualTo($from)) {
            $slot = $slot->addHours($interval);
        }

        return $slot;
    }
}
