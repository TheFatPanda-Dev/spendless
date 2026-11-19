<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_authenticated_dashboard_responses_disable_browser_caching(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control', '');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
    }

    public function test_dashboard_defaults_to_the_current_month()
    {
        Carbon::setTestNow('2026-03-09 12:00:00');

        $user = User::factory()->create();
        $this->actingAs($user);

        try {
            $response = $this->get(route('dashboard'));

            $response
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('dashboard')
                    ->where('month.start', '2026-03-01')
                    ->where('month.end', '2026-03-31'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_uses_requested_date_filters()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard', [
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
        ]));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('month.start', '2026-03-01')
                ->where('month.end', '2026-03-31'));
    }

    public function test_an_empty_month_has_no_budget_spending_or_activity(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('baseCurrency', 'EUR')
                ->where('number_locale', 'en-GB')
                ->where('budget', null)
                ->where('spent', 0)
                ->where('income', 0)
                ->where('categories', [])
                ->where('recent', [])
                ->where('netWorth.by_currency', []));
    }

    public function test_dashboard_uses_users_saved_base_currency_preference(): void
    {
        $user = User::factory()->create([
            'preferred_base_currency' => 'GBP',
            'preferred_number_locale' => 'de-DE',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('baseCurrency', 'GBP')
                ->where('number_locale', 'de-DE'));
    }
}
