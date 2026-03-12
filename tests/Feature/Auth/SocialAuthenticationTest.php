<?php

namespace Tests\Feature\Auth;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, list<string>>
     */
    private function expectedDefaultMainCategories(): array
    {
        return [
            'expense' => [
                'Housing',
                'Transportation',
                'Groceries',
                'Food & Drink',
                'Health & Wellness',
                'Personal Care',
                'Shopping',
                'Entertainment',
                'Subscriptions',
                'Travel & Vacation',
                'Debt Repayment',
                'Savings & Goals',
                'Investments',
                'Education',
                'Family & Dependents',
                'Gifts & Occasions',
                'Giving & Donations',
                'Other',
            ],
            'income' => [
                'Wages & Salary',
                'Self-Employment & Freelance',
                'Investment Dividends',
                'Stock Capital Gains',
                'Interest Income',
                'Rental & Real Estate Income',
                'Tips & Gratuities',
                'Gifts & Inheritance',
                'Government Benefits & Subsidies',
                'Tax Refunds',
                'Pensions & Retirement Distributions',
                'Alimony & Child Support',
                'Grants & Scholarships',
                'Royalties',
                'Side Hustles',
                'Refunds & Reimbursements',
                'Sales (Selling Personal Items)',
                'Other Income',
            ],
        ];
    }

    private function assertDefaultMainCategoriesCreatedFor(User $user): void
    {
        foreach ($this->expectedDefaultMainCategories() as $type => $expectedCategories) {
            $this->assertSame(
                $expectedCategories,
                Category::query()
                    ->where('user_id', $user->id)
                    ->whereNull('parent_id')
                    ->where('type', $type)
                    ->orderBy('id')
                    ->pluck('name')
                    ->all(),
            );
        }
    }

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

    public function test_user_cannot_login_with_google_when_account_does_not_exist()
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

        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'panda@example.com',
        ]);
        $response->assertRedirect(route('login', absolute: false));
        $response->assertSessionHasErrors('email');
        $response->assertSessionHas('oauth_prompt', [
            'provider' => 'Google',
            'email' => 'panda@example.com',
        ]);
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
            'google_avatar' => 'https://example.com/avatar-2.png',
        ]);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_user_cannot_login_with_google_when_account_does_not_exist_even_with_avatar_in_raw_payload(): void
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
                            /** @var array<string, mixed> */
                            public array $user = [
                                'picture' => 'https://example.com/avatar-from-picture.png',
                                'email_verified' => true,
                            ];

                            public function getId(): string
                            {
                                return 'google-456';
                            }

                            public function getName(): string
                            {
                                return 'Picture Panda';
                            }

                            public function getEmail(): string
                            {
                                return 'picture@example.com';
                            }

                            public function getAvatar(): string
                            {
                                return '';
                            }
                        };
                    }
                };
            }
        });

        $response = $this->get(route('google.callback'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'picture@example.com',
        ]);
        $response->assertRedirect(route('login', absolute: false));
        $response->assertSessionHasErrors('email');
        $response->assertSessionHas('oauth_prompt', [
            'provider' => 'Google',
            'email' => 'picture@example.com',
        ]);
    }

    public function test_google_popup_callback_returns_prompt_payload_when_account_does_not_exist(): void
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
                                return 'google-popup-missing-1';
                            }

                            public function getName(): string
                            {
                                return 'Popup Missing User';
                            }

                            public function getEmail(): string
                            {
                                return 'popup-missing@example.com';
                            }

                            public function getAvatar(): string
                            {
                                return 'https://example.com/popup-missing-avatar.png';
                            }
                        };
                    }
                };
            }
        });

        $response = $this->withSession([
            'oauth_popup_google' => true,
        ])->get(route('google.callback'));

        $response->assertOk();
        $response->assertViewIs('auth.oauth-popup');
        $response->assertViewHas('payload', [
            'type' => 'prompt',
            'provider' => 'Google',
            'email' => 'popup-missing@example.com',
        ]);
        $response->assertSessionHas('oauth_registration_candidate');
    }

    public function test_google_popup_callback_returns_success_payload_for_existing_user(): void
    {
        $user = User::factory()->create([
            'email' => 'popup-existing@example.com',
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
                                return 'google-popup-existing-1';
                            }

                            public function getName(): string
                            {
                                return 'Popup Existing User';
                            }

                            public function getEmail(): string
                            {
                                return 'popup-existing@example.com';
                            }

                            public function getAvatar(): string
                            {
                                return 'https://example.com/popup-existing-avatar.png';
                            }
                        };
                    }
                };
            }
        });

        $response = $this->withSession([
            'oauth_popup_google' => true,
        ])->get(route('google.callback'));

        $response->assertOk();
        $response->assertViewIs('auth.oauth-popup');
        $response->assertViewHas('payload', [
            'type' => 'success',
            'redirect' => route('dashboard', absolute: false),
        ]);
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_user_cannot_login_with_github_when_account_does_not_exist(): void
    {
        Http::fake([
            'https://api.github.com/user/emails' => Http::response([
                [
                    'email' => 'new-github-user@example.com',
                    'primary' => true,
                    'verified' => true,
                ],
            ], 200),
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
                            public string $token = 'fake-github-token';

                            public function getId(): string
                            {
                                return 'github-new-1';
                            }

                            public function getName(): string
                            {
                                return 'New GitHub User';
                            }

                            public function getEmail(): string
                            {
                                return 'new-github-user@example.com';
                            }

                            public function getAvatar(): string
                            {
                                return 'https://example.com/new-github-avatar.png';
                            }
                        };
                    }
                };
            }
        });

        $response = $this->get(route('github.callback'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'new-github-user@example.com',
        ]);
        $response->assertRedirect(route('login', absolute: false));
        $response->assertSessionHasErrors('email');
        $response->assertSessionHas('oauth_prompt', [
            'provider' => 'GitHub',
            'email' => 'new-github-user@example.com',
        ]);
    }

    public function test_user_can_confirm_oauth_prompt_and_register_with_google(): void
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
                                return 'google-prompt-1';
                            }

                            public function getName(): string
                            {
                                return 'Prompt Panda';
                            }

                            public function getEmail(): string
                            {
                                return 'prompt-google@example.com';
                            }

                            public function getAvatar(): string
                            {
                                return 'https://example.com/prompt-google-avatar.png';
                            }
                        };
                    }
                };
            }
        });

        $this->get(route('google.callback'));

        $response = $this->post(route('oauth.register'));

        $response->assertRedirect(route('security.edit', absolute: false).'#oauth');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'prompt-google@example.com',
            'google_id' => 'google-prompt-1',
            'google_avatar' => 'https://example.com/prompt-google-avatar.png',
        ]);
        $this->assertDefaultMainCategoriesCreatedFor(
            User::query()->where('email', 'prompt-google@example.com')->firstOrFail(),
        );
    }

    public function test_user_can_confirm_oauth_prompt_and_register_with_github(): void
    {
        Http::fake([
            'https://api.github.com/user/emails' => Http::response([
                [
                    'email' => 'prompt-github@example.com',
                    'primary' => true,
                    'verified' => true,
                ],
            ], 200),
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
                            public string $token = 'fake-github-token';

                            public function getId(): string
                            {
                                return 'github-prompt-1';
                            }

                            public function getName(): string
                            {
                                return 'Prompt GitHub User';
                            }

                            public function getEmail(): string
                            {
                                return 'prompt-github@example.com';
                            }

                            public function getAvatar(): string
                            {
                                return 'https://example.com/prompt-github-avatar.png';
                            }
                        };
                    }
                };
            }
        });

        $this->get(route('github.callback'));

        $response = $this->post(route('oauth.register'));

        $response->assertRedirect(route('security.edit', absolute: false).'#oauth');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'prompt-github@example.com',
            'github_id' => 'github-prompt-1',
            'github_avatar' => 'https://example.com/prompt-github-avatar.png',
        ]);
        $this->assertDefaultMainCategoriesCreatedFor(
            User::query()->where('email', 'prompt-github@example.com')->firstOrFail(),
        );
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

        $response->assertRedirect(route('security.edit', absolute: false).'#oauth');

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

        $response->assertRedirect(route('security.edit', absolute: false).'#oauth');

        $user->refresh();

        $this->assertSame('github-link-1', $user->github_id);
        $this->assertSame('https://example.com/github-link-avatar.png', $user->github_avatar);
    }

    public function test_user_can_register_with_google_from_register_page_intent(): void
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
                                return 'google-register-intent-1';
                            }

                            public function getName(): string
                            {
                                return 'Register Intent Panda';
                            }

                            public function getEmail(): string
                            {
                                return 'register-intent-google@example.com';
                            }

                            public function getAvatar(): string
                            {
                                return 'https://example.com/register-intent-google-avatar.png';
                            }
                        };
                    }
                };
            }
        });

        $response = $this->withSession([
            'oauth_register_intent_google' => true,
        ])->get(route('google.callback'));

        $response->assertRedirect(route('security.edit', absolute: false).'#oauth');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'register-intent-google@example.com',
            'google_id' => 'google-register-intent-1',
            'google_avatar' => 'https://example.com/register-intent-google-avatar.png',
        ]);
    }

    public function test_user_can_register_with_github_from_register_page_intent(): void
    {
        Http::fake([
            'https://api.github.com/user/emails' => Http::response([
                [
                    'email' => 'register-intent-github@example.com',
                    'primary' => true,
                    'verified' => true,
                ],
            ], 200),
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
                            public string $token = 'fake-register-intent-github-token';

                            public function getId(): string
                            {
                                return 'github-register-intent-1';
                            }

                            public function getName(): string
                            {
                                return 'Register Intent GitHub User';
                            }

                            public function getEmail(): string
                            {
                                return 'register-intent-github@example.com';
                            }

                            public function getAvatar(): string
                            {
                                return 'https://example.com/register-intent-github-avatar.png';
                            }
                        };
                    }
                };
            }
        });

        $response = $this->withSession([
            'oauth_register_intent_github' => true,
        ])->get(route('github.callback'));

        $response->assertRedirect(route('security.edit', absolute: false).'#oauth');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'register-intent-github@example.com',
            'github_id' => 'github-register-intent-1',
            'github_avatar' => 'https://example.com/register-intent-github-avatar.png',
        ]);
    }
}
