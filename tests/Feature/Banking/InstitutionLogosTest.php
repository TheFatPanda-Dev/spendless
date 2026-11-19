<?php

namespace Tests\Feature\Banking;

use App\Models\BankConnection;
use App\Models\User;
use App\Support\Banking\InstitutionLogos;
use App\Support\Categories\CategoryOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class InstitutionLogosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        InstitutionLogos::forget();
    }

    public function test_known_institutions_are_matched_by_whole_words(): void
    {
        $this->assertSame('barclays.co.uk', InstitutionLogos::domainFor('Barclays'));
        $this->assertSame('barclaycard.co.uk', InstitutionLogos::domainFor('Barclaycard'));
        $this->assertSame('alrayanbank.co.uk', InstitutionLogos::domainFor('Al Rayan Bank'));
        $this->assertSame('interactivebrokers.com', InstitutionLogos::domainFor('IBKR Individual', BankConnection::PROVIDER_IBKR));
        $this->assertNull(InstitutionLogos::domainFor('Otherwise Savings'));
        $this->assertNull(InstitutionLogos::domainFor(null));
    }

    public function test_the_sites_own_touch_icon_is_stored_and_svg_is_refused(): void
    {
        $png = "\x89PNG\r\n\x1a\n".str_repeat('x', 200);

        Http::fake([
            'https://www.barclays.co.uk/' => Http::response(
                '<html><head><link rel="icon" href="/logo.svg"><link rel="apple-touch-icon" sizes="180x180" href="/touch.png"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'https://www.barclays.co.uk/touch.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
            '*' => Http::response('', 404),
        ]);

        $this->assertTrue(InstitutionLogos::fetch('barclays.co.uk'));

        Storage::disk('public')->assertExists('institution-logos/barclays.co.uk.png');
        Http::assertNotSent(fn ($request): bool => Str::endsWith($request->url(), '.svg'));

        $connection = $this->connection('Barclays', BankConnection::PROVIDER_IMPORT);
        $this->assertSame('/storage/institution-logos/barclays.co.uk.png', $connection->logoUrl());
        $this->assertSame('/storage/institution-logos/barclays.co.uk.png', $connection->toStatusArray()['institution_logo']);
    }

    public function test_a_failed_fetch_is_not_retried_for_a_week(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $this->assertFalse(InstitutionLogos::fetch('pensionbee.com'));
        $this->assertTrue(InstitutionLogos::recentlyMissed('pensionbee.com'));
        $this->assertNull(InstitutionLogos::url('PensionBee'));

        $this->connection('PensionBee', BankConnection::PROVIDER_ASSET);
        Http::fake(['*' => Http::response('', 404)]);

        $this->artisan('banking:fetch-logos')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_a_new_wallet_gets_its_logo_right_after_the_response(): void
    {
        config(['services.institution_logos.fetch_on_save' => true]);
        $png = "\x89PNG\r\n\x1a\n".str_repeat('x', 200);

        Http::fake([
            'https://assets.revolut.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
            '*' => Http::response('', 403),
        ]);

        $connection = $this->connection('Revolut', BankConnection::PROVIDER_IMPORT);
        Http::assertNothingSent();

        $this->app->terminate();

        Storage::disk('public')->assertExists('institution-logos/revolut.com.png');
        $this->assertSame('/storage/institution-logos/revolut.com.png', $connection->fresh()->logoUrl());
        Http::assertSentCount(1);
    }

    public function test_unknown_domains_are_never_fetched(): void
    {
        Http::fake();

        $this->assertFalse(InstitutionLogos::fetch('example.com'));
        Http::assertNothingSent();
    }

    public function test_category_colours_resolve_to_tile_tints(): void
    {
        $this->assertSame('rgb(16 185 129)', CategoryOptions::tint('emerald'));
        $this->assertSame('#abcdef', CategoryOptions::tint('#ABCDEF'));
        $this->assertNull(CategoryOptions::tint('not-a-colour'));
        $this->assertNull(CategoryOptions::tint(null));
    }

    private function connection(string $institution, string $provider): BankConnection
    {
        return BankConnection::query()->create([
            'user_id' => User::factory()->create()->id,
            'provider' => $provider,
            'institution_name' => $institution,
            'aspsp_name' => $institution,
            'aspsp_country' => 'GB',
            'state' => (string) Str::uuid(),
            'status' => BankConnection::STATUS_CONNECTED,
        ]);
    }
}
