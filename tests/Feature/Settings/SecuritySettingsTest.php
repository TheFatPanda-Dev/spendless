<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SecuritySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/security')
                ->where('oauth.googleLinked', false)
                ->where('oauth.githubLinked', false)
                ->where('password.hasPasswordSet', true)
            );
    }

    public function test_security_page_shows_linked_oauth_provider_status(): void
    {
        $user = User::factory()->create([
            'google_id' => 'google-123',
            'github_id' => 'github-456',
        ]);

        $this->actingAs($user)
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/security')
                ->where('oauth.googleLinked', true)
                ->where('oauth.githubLinked', true)
                ->where('password.hasPasswordSet', true)
            );
    }
}
