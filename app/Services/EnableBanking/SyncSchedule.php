<?php

namespace App\Services\EnableBanking;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Background syncs run at fixed local times of day (default 07:00, 11:00, 15:00, 19:00
 * Europe/Ljubljana): every 4 hours during the day and 4 a day in total, which is the PSD2
 * limit for refreshes without the account holder present. "Sync now" is separate and
 * always runs immediately.
 */
final class SyncSchedule
{
    private const DEFAULT_HOURS = [7, 11, 15, 19];

    /**
     * @return list<int> Hours of the day (0–23), ascending.
     */
    public static function hours(): array
    {
        return self::parseHours((string) config('services.enable_banking.sync_hours', ''), self::DEFAULT_HOURS);
    }

    public static function timezone(): string
    {
        $timezone = (string) config('services.enable_banking.sync_timezone', 'Europe/Ljubljana');

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'UTC';
    }

    /**
     * Parse a "7,11,15,19" style list of hours; falls back to $default when nothing valid.
     *
     * @param  list<int>  $default
     * @return list<int>
     */
    public static function parseHours(string $value, array $default): array
    {
        $hours = collect(explode(',', $value))
            ->map(fn (string $hour): string => trim($hour))
            ->filter(fn (string $hour): bool => ctype_digit($hour) && (int) $hour <= 23)
            ->map(fn (string $hour): int => (int) $hour)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $hours === [] ? $default : $hours;
    }

    /**
     * The first sync time strictly after $from, returned in $from's timezone.
     *
     * @param  list<int>|null  $hours  Defaults to the bank-sync hours.
     */
    public static function nextSlot(?CarbonInterface $from = null, ?array $hours = null): CarbonImmutable
    {
        $from = CarbonImmutable::instance($from ?? now());
        $today = $from->setTimezone(self::timezone())->startOfDay();
        $hours = $hours === null || $hours === [] ? self::hours() : array_values($hours);

        foreach ([$today, $today->addDay()] as $day) {
            foreach ($hours as $hour) {
                $slot = $day->setTime($hour, 0);

                if ($slot->greaterThan($from)) {
                    return $slot->setTimezone($from->getTimezone());
                }
            }
        }

        return $today->addDays(2)->setTime($hours[0], 0)->setTimezone($from->getTimezone());
    }

    /**
     * e.g. "07:00, 11:00, 15:00 and 19:00 (Europe/Ljubljana)".
     */
    public static function describe(): string
    {
        $times = array_map(fn (int $hour): string => sprintf('%02d:00', $hour), self::hours());
        $last = array_pop($times);
        $list = $times === [] ? $last : implode(', ', $times).' and '.$last;

        return sprintf('%s (%s)', $list, self::timezone());
    }
}
