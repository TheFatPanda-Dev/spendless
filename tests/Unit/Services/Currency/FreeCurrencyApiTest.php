<?php

namespace Tests\Unit\Services\Currency;

use App\Services\Currency\FreeCurrencyApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class FreeCurrencyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_fallback_rates_when_api_key_is_missing(): void
    {
        config()->set('services.freecurrencyapi.key', '');

        $service = app(FreeCurrencyApi::class);

        $rates = $service->eurPerUnit(['GBP', 'USD']);

        $this->assertSame(1.0, $rates['EUR']);
        $this->assertSame(1.17, $rates['GBP']);
        $this->assertSame(0.92, $rates['USD']);
    }

    public function test_it_caches_latest_rates_and_reuses_cached_data(): void
    {
        config()->set('services.freecurrencyapi.key', 'test-key');
        config()->set('services.freecurrencyapi.cache_ttl_seconds', 86400);
        config()->set('services.freecurrencyapi.stale_cache_ttl_seconds', 604800);
        config()->set('services.freecurrencyapi.max_requests_per_minute', 10);

        Cache::flush();
        RateLimiter::clear('freecurrencyapi:latest:minute');

        Http::fake([
            'https://api.freecurrencyapi.com/v1/latest*' => Http::response([
                'data' => [
                    'GBP' => 0.85,
                    'USD' => 1.1,
                ],
            ], 200),
        ]);

        $service = app(FreeCurrencyApi::class);

        $firstCallRates = $service->eurPerUnit(['GBP', 'USD']);
        $secondCallRates = $service->eurPerUnit(['GBP', 'USD']);

        $this->assertEqualsWithDelta(1.1764705882, $firstCallRates['GBP'], 0.0001);
        $this->assertEqualsWithDelta(0.9090909091, $firstCallRates['USD'], 0.0001);
        $this->assertEqualsWithDelta($firstCallRates['GBP'], $secondCallRates['GBP'], 0.00001);
        $this->assertEqualsWithDelta($firstCallRates['USD'], $secondCallRates['USD'], 0.00001);

        Http::assertSentCount(1);
    }

    public function test_it_skips_outbound_call_when_daily_budget_is_exhausted(): void
    {
        config()->set('services.freecurrencyapi.key', 'test-key');
        config()->set('services.freecurrencyapi.monthly_request_limit', 1);
        config()->set('services.freecurrencyapi.monthly_reserve', 0);
        config()->set('services.freecurrencyapi.max_requests_per_minute', 10);

        Cache::flush();
        RateLimiter::clear('freecurrencyapi:latest:minute');

        Cache::put(
            'freecurrencyapi:quota:day:'.now()->format('Ymd'),
            1,
            now()->endOfDay(),
        );

        Http::fake();

        $service = app(FreeCurrencyApi::class);

        $rates = $service->eurPerUnit(['GBP']);

        $this->assertSame(1.17, $rates['GBP']);
        Http::assertSentCount(0);
    }
}
