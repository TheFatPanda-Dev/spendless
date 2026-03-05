<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_settings_route_redirects_to_security_page()
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('two-factor.show'))
            ->assertRedirect(route('security.edit'));
    }

    public function test_two_factor_settings_route_redirects_to_security_without_password_confirmation_when_enabled()
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        $user = User::factory()->create();

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('two-factor.show'));

        $response->assertRedirect(route('security.edit'));
    }

    public function test_two_factor_settings_route_redirects_to_security_without_password_confirmation_when_disabled()
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        $user = User::factory()->create();

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => false,
        ]);

        $this->actingAs($user)
            ->get(route('two-factor.show'))
            ->assertRedirect(route('security.edit'));
    }

    public function test_two_factor_settings_route_redirects_to_security_when_two_factor_is_disabled()
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        config(['fortify.features' => []]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('two-factor.show'))
            ->assertRedirect(route('security.edit'));
    }

    public function test_user_without_password_cannot_enable_two_factor_authentication(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        $user = User::factory()->create([
            'password_set_at' => null,
        ]);

        $this->actingAs($user)
            ->post('/user/two-factor-authentication')
            ->assertRedirect(route('security.edit'))
            ->assertSessionHas('error', 'Set a password before enabling two-factor authentication.');
    }

    public function test_user_with_password_can_reach_enable_two_factor_authentication_flow(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        $user = User::factory()->create([
            'password_set_at' => now(),
        ]);

        $this->actingAs($user)
            ->post('/user/two-factor-authentication')
            ->assertSessionMissing('error');
    }
}
