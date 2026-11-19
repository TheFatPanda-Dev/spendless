<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_successful_response()
    {
        $response = $this->get(route('home'));

        $response->assertOk();
    }

    public function test_home_offers_registration_while_sign_ups_are_open(): void
    {
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->where('canRegister', true));
    }

    public function test_home_hides_registration_when_sign_ups_are_closed(): void
    {
        AppSetting::putValue(AppSetting::REGISTRATION_OPEN, '0');

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->where('canRegister', false));
    }

    public function test_home_has_link_preview_tags(): void
    {
        $this->get(route('home'))
            ->assertSee('<meta property="og:title" content="SpendLess — Money made simple">', false)
            ->assertSee(url('/images/brand/link-preview.png'), false);
    }

    public function test_other_pages_do_not_carry_home_link_preview_tags(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertDontSee('og:title', false);
    }

    public function test_authenticated_user_is_redirected_from_home_to_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertRedirect(route('dashboard'));
    }
}
