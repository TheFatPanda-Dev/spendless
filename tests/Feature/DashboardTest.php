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
                    ->where('filters.start_date', '2026-03-01')
                    ->where('filters.end_date', '2026-03-31'));
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
                ->where('filters.start_date', '2026-03-01')
                ->where('filters.end_date', '2026-03-31'));
    }
}
