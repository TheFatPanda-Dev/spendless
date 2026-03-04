<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_redirect_route_redirects_to_google_provider()
    {
        app()->instance('Laravel\\Socialite\\Contracts\\Factory', new class
        {
            public function driver(string $driver): object
            {
                return new class
                {
                    public function redirect()
                    {
                        return redirect('https://accounts.google.com/o/oauth2/auth');
                    }
                };
            }
        });

        $response = $this->get(route('google.redirect'));

        $response->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_user_can_register_and_authenticate_with_google()
    {
        app()->instance('Laravel\\Socialite\\Contracts\\Factory', new class
        {
            public function driver(string $driver): object
            {
                return new class
                {
                    public function user(): object
                    {
                        return new class
                        {
                            public function getId(): string
                            {
                                return 'google-123';
                            }

                            public function getName(): string
                            {
                                return 'Test Panda';
                            }

                            public function getEmail(): string
                            {
                                return 'panda@example.com';
                            }

                            public function getAvatar(): string
                            {
                                return 'https://example.com/avatar.png';
                            }
                        };
                    }
                };
            }
        });

        $response = $this->get(route('google.callback'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'panda@example.com',
            'google_id' => 'google-123',
        ]);
        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertSessionHas('success', 'Registration successful');
    }

    public function test_existing_user_can_login_with_google_using_same_email()
    {
        $user = User::factory()->create([
            'email' => 'panda@example.com',
            'google_id' => null,
        ]);

        app()->instance('Laravel\\Socialite\\Contracts\\Factory', new class
        {
            public function driver(string $driver): object
            {
                return new class
                {
                    public function user(): object
                    {
                        return new class
                        {
                            public function getId(): string
                            {
                                return 'google-789';
                            }

                            public function getName(): string
                            {
                                return 'Test Panda';
                            }

                            public function getEmail(): string
                            {
                                return 'panda@example.com';
                            }

                            public function getAvatar(): string
                            {
                                return 'https://example.com/avatar-2.png';
                            }
                        };
                    }
                };
            }
        });

        $response = $this->get(route('google.callback'));

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id' => 'google-789',
        ]);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_authenticated_user_can_link_google_account_from_profile_settings(): void
    {
        $user = User::factory()->create([
            'google_id' => null,
            'google_avatar' => null,
        ]);

        app()->instance('Laravel\\Socialite\\Contracts\\Factory', new class
        {
            public function driver(string $driver): object
            {
                return new class
                {
                    public function redirectUrl(string $url): self
                    {
                        return $this;
                    }

                    public function user(): object
                    {
                        return new class
                        {
                            public function getId(): string
                            {
                                return 'google-link-1';
                            }

                            public function getAvatar(): string
                            {
                                return 'https://example.com/google-link-avatar.png';
                            }
                        };
                    }
                };
            }
        });

        $response = $this->actingAs($user)->get(route('settings.google.callback'));

        $response->assertRedirect(route('profile.edit', absolute: false));

        $user->refresh();

        $this->assertSame('google-link-1', $user->google_id);
        $this->assertSame('https://example.com/google-link-avatar.png', $user->google_avatar);
    }

    public function test_authenticated_user_can_link_github_account_from_profile_settings(): void
    {
        Http::fake([
            'https://api.github.com/user/emails' => Http::response([
                [
                    'email' => 'linked-github@example.com',
                    'primary' => true,
                    'verified' => true,
                ],
            ], 200),
        ]);

        $user = User::factory()->create([
            'github_id' => null,
            'github_avatar' => null,
        ]);

        app()->instance('Laravel\\Socialite\\Contracts\\Factory', new class
        {
            public function driver(string $driver): object
            {
                return new class
                {
                    public function redirectUrl(string $url): self
                    {
                        return $this;
                    }

                    public function user(): object
                    {
                        return new class
                        {
                            public string $token = 'fake-github-token';

                            public function getId(): string
                            {
                                return 'github-link-1';
                            }

                            public function getEmail(): string
                            {
                                return 'linked-github@example.com';
                            }

                            public function getAvatar(): string
                            {
                                return 'https://example.com/github-link-avatar.png';
                            }
                        };
                    }
                };
            }
        });

        $response = $this->actingAs($user)->get(route('settings.github.callback'));

        $response->assertRedirect(route('profile.edit', absolute: false));

        $user->refresh();

        $this->assertSame('github-link-1', $user->github_id);
        $this->assertSame('https://example.com/github-link-avatar.png', $user->github_avatar);
    }
}
