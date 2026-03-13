<?php

namespace App\Services\Currency;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class FreeCurrencyApi
{
    private const FRESH_CACHE_KEY = 'freecurrencyapi:latest:eur-per-unit:fresh';

    private const STALE_CACHE_KEY = 'freecurrencyapi:latest:eur-per-unit:stale';

    private const MINUTE_LIMITER_KEY = 'freecurrencyapi:latest:minute';

    private const DAILY_QUOTA_KEY_PREFIX = 'freecurrencyapi:quota:day:';

    private const MONTHLY_QUOTA_KEY_PREFIX = 'freecurrencyapi:quota:month:';

    private const DEFAULT_EUR_PER_UNIT = [
        'EUR' => 1.0,
        'GBP' => 1.17,
        'USD' => 0.92,
    ];

    public function eurPerUnit(array $currencies): array
    {
        $requestedCurrencies = collect($currencies)
            ->map(fn (mixed $currency): string => strtoupper((string) $currency))
            ->filter()
            ->push('EUR')
            ->unique()
            ->sort()
            ->values();

        $freshRates = Cache::get(self::FRESH_CACHE_KEY);

        if (is_array($freshRates) && $freshRates !== []) {
            return $this->pickCurrencies($freshRates, $requestedCurrencies->all());
        }

        $apiKey = (string) config('services.freecurrencyapi.key');

        if ($apiKey === '') {
            return $this->fallbackFor($requestedCurrencies->all());
        }

        $maxRequestsPerMinute = (int) config('services.freecurrencyapi.max_requests_per_minute', 10);

        if (RateLimiter::tooManyAttempts(self::MINUTE_LIMITER_KEY, max($maxRequestsPerMinute, 1))) {
            $staleRates = Cache::get(self::STALE_CACHE_KEY);

            if (is_array($staleRates) && $staleRates !== []) {
                return $this->pickCurrencies($staleRates, $requestedCurrencies->all());
            }

            return $this->fallbackFor($requestedCurrencies->all());
        }

        if (! $this->consumeQuotaBudget()) {
            return $this->staleOrFallback($requestedCurrencies->all());
        }

        RateLimiter::hit(self::MINUTE_LIMITER_KEY, 60);

        try {
            $response = Http::baseUrl((string) config('services.freecurrencyapi.base_url', 'https://api.freecurrencyapi.com'))
                ->acceptJson()
                ->timeout((int) config('services.freecurrencyapi.timeout_seconds', 8))
                ->get('/v1/latest', [
                    'apikey' => $apiKey,
                    'base_currency' => 'EUR',
                ]);

            if (! $response->ok()) {
                Log::warning('freecurrencyapi latest endpoint failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return $this->staleOrFallback($requestedCurrencies->all());
            }

            $ratesByEur = collect((array) data_get($response->json(), 'data', []));

            $allKnownCurrencies = collect(self::DEFAULT_EUR_PER_UNIT)
                ->keys()
                ->merge($ratesByEur->keys())
                ->push('EUR')
                ->unique()
                ->values();

            $eurPerUnit = $allKnownCurrencies
                ->mapWithKeys(function (string $currency) use ($ratesByEur): array {
                    if ($currency === 'EUR') {
                        return ['EUR' => 1.0];
                    }

                    $rate = (float) ($ratesByEur->get($currency) ?? 0);

                    if ($rate <= 0) {
                        $fallbackRate = self::DEFAULT_EUR_PER_UNIT[$currency] ?? 1.0;

                        return [$currency => $fallbackRate];
                    }

                    return [$currency => 1 / $rate];
                })
                ->all();

            $freshTtlSeconds = (int) config('services.freecurrencyapi.cache_ttl_seconds', 3600);
            $staleTtlSeconds = (int) config('services.freecurrencyapi.stale_cache_ttl_seconds', 604800);

            Cache::put(
                self::FRESH_CACHE_KEY,
                $eurPerUnit,
                now()->addSeconds(max($freshTtlSeconds, 3600)),
            );

            Cache::put(
                self::STALE_CACHE_KEY,
                $eurPerUnit,
                now()->addSeconds(max($staleTtlSeconds, max($freshTtlSeconds, 3600))),
            );

            return $this->pickCurrencies($eurPerUnit, $requestedCurrencies->all());
        } catch (\Throwable $exception) {
            Log::warning('freecurrencyapi latest endpoint exception', [
                'message' => $exception->getMessage(),
            ]);

            return $this->staleOrFallback($requestedCurrencies->all());
        }
    }

    private function fallbackFor(array $currencies): array
    {
        return collect($currencies)
            ->mapWithKeys(fn (string $currency): array => [
                $currency => self::DEFAULT_EUR_PER_UNIT[$currency] ?? 1.0,
            ])
            ->all();
    }

    private function staleOrFallback(array $currencies): array
    {
        $staleRates = Cache::get(self::STALE_CACHE_KEY);

        if (is_array($staleRates) && $staleRates !== []) {
            return $this->pickCurrencies($staleRates, $currencies);
        }

        return $this->fallbackFor($currencies);
    }

    private function pickCurrencies(array $eurPerUnit, array $currencies): array
    {
        return collect($currencies)
            ->mapWithKeys(fn (string $currency): array => [
                $currency => (float) ($eurPerUnit[$currency] ?? self::DEFAULT_EUR_PER_UNIT[$currency] ?? 1.0),
            ])
            ->all();
    }

    private function consumeQuotaBudget(): bool
    {
        $monthlyLimit = (int) config('services.freecurrencyapi.monthly_request_limit', 1000);

        if ($monthlyLimit <= 0) {
            return true;
        }

        $monthlyReserve = max((int) config('services.freecurrencyapi.monthly_reserve', 0), 0);
        $effectiveMonthlyBudget = max($monthlyLimit - $monthlyReserve, 1);
        $daysInMonth = max(now()->daysInMonth, 1);
        $dailyBudget = max(intdiv($effectiveMonthlyBudget, $daysInMonth), 1);

        $dailyKey = self::DAILY_QUOTA_KEY_PREFIX.now()->format('Ymd');
        $monthlyKey = self::MONTHLY_QUOTA_KEY_PREFIX.now()->format('Ym');

        Cache::add($dailyKey, 0, now()->endOfDay());
        Cache::add($monthlyKey, 0, now()->endOfMonth());

        $dailyCount = (int) Cache::get($dailyKey, 0);
        $monthlyCount = (int) Cache::get($monthlyKey, 0);

        if ($dailyCount >= $dailyBudget || $monthlyCount >= $effectiveMonthlyBudget) {
            return false;
        }

        Cache::increment($dailyKey);
        Cache::increment($monthlyKey);

        return true;
    }
}
