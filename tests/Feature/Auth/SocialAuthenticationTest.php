<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'nickname' => 'panda',
        ]);
        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertSessionHas('success', 'Registration successful');
    }

    public function test_existing_user_can_login_with_google_using_same_email()
    {
        $user = User::factory()->create([
            'email' => 'panda@example.com',
            'nickname' => 'panda',
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
}
