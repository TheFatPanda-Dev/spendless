<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Notifications\AccountDeletedNotification;
use App\Notifications\ConfirmEmailChangeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/profile')
                ->where('oauth.googleLinked', false)
                ->where('oauth.githubLinked', false)
                ->where('password.hasPasswordSet', true)
            );
    }

    public function test_profile_page_shows_linked_oauth_providers_status(): void
    {
        $user = User::factory()->create([
            'google_id' => 'google-123',
            'github_id' => 'github-456',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/profile')
                ->where('oauth.googleLinked', true)
                ->where('oauth.githubLinked', true)
                ->where('password.hasPasswordSet', true)
            );
    }

    public function test_appearance_page_redirects_to_profile_page(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('appearance.edit'))
            ->assertRedirect(route('profile.edit'));
    }

    public function test_profile_page_falls_back_to_google_avatar_when_local_avatar_file_is_missing(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'avatar_path' => 'avatars/missing-avatar.png',
            'google_avatar' => 'https://example.com/google-fallback.png',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/profile')
                ->where('auth.user.avatar', 'https://example.com/google-fallback.png')
            );
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'preferred_name' => 'Panda',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('Panda', $user->preferred_name);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_email_change_request_sends_confirmation_to_new_email_and_keeps_current_email_until_confirmed(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $currentEmail = $user->email;
        $newEmail = 'new-email@example.com';

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'new_email' => $newEmail,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame($currentEmail, $user->email);
        $this->assertSame($newEmail, $user->pending_email);

        Notification::assertSentOnDemand(ConfirmEmailChangeNotification::class);
    }

    public function test_new_email_must_be_different_from_current_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'new_email' => $user->email,
            ]);

        $response
            ->assertSessionHasErrors([
                'new_email' => 'The new email address must be different from your current email address.',
            ])
            ->assertRedirect(route('profile.edit'));

        Notification::assertNothingSent();

        $user->refresh();

        $this->assertNull($user->pending_email);
    }

    public function test_email_is_updated_only_after_signed_confirmation_link_is_opened(): void
    {
        $user = User::factory()->create([
            'pending_email' => 'new-email@example.com',
            'pending_email_requested_at' => now(),
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'profile.email.confirm',
            now()->addMinutes(30),
            [
                'user' => $user->id,
                'email' => 'new-email@example.com',
            ],
            absolute: false,
        );

        $response = $this->actingAs($user)->get($signedUrl);

        $response->assertRedirect(route('profile.edit', absolute: false));

        $user->refresh();

        $this->assertSame('new-email@example.com', $user->email);
        $this->assertNull($user->pending_email);
        $this->assertNull($user->pending_email_requested_at);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_profile_avatar_can_be_uploaded(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'avatar' => UploadedFile::fake()->image('avatar.png', 128, 128),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_profile_avatar_can_be_uploaded_with_post_method_spoofing_patch(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('profile.update'), [
                '_method' => 'PATCH',
                'name' => $user->name,
                'avatar' => UploadedFile::fake()->image('avatar-spoofed.png', 128, 128),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_replacing_profile_avatar_deletes_previous_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'avatar_path' => 'avatars/old-avatar.png',
        ]);

        Storage::disk('public')->put('avatars/old-avatar.png', 'old-image');

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'avatar' => UploadedFile::fake()->image('new-avatar.png', 128, 128),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertNotNull($user->avatar_path);
        $this->assertNotSame('avatars/old-avatar.png', $user->avatar_path);
        Storage::disk('public')->assertMissing('avatars/old-avatar.png');
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_user_can_delete_their_account()
    {
        Notification::fake();

        $user = User::factory()->create();
        $deletedEmail = $user->email;

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());

        Notification::assertSentOnDemand(
            AccountDeletedNotification::class,
            function (AccountDeletedNotification $notification, array $channels, object $notifiable) use ($deletedEmail): bool {
                return in_array('mail', $channels, true)
                    && isset($notifiable->routes['mail'])
                    && $notifiable->routes['mail'] === $deletedEmail;
            },
        );
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }

    public function test_account_cannot_be_deleted_when_password_is_not_set(): void
    {
        $user = User::factory()->create([
            'password_set_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertRedirect(route('security.edit'))
            ->assertSessionHas('error', 'Password not set. Set a password before deleting your account.');

        $this->assertNotNull($user->fresh());
    }

    public function test_google_link_can_be_removed_when_user_has_password_set(): void
    {
        $user = User::factory()->create([
            'google_id' => 'google-1',
            'google_avatar' => 'https://example.com/google.png',
            'password_set_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('settings.google.unlink'));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $user->refresh();

        $this->assertNull($user->google_id);
        $this->assertNull($user->google_avatar);
    }

    public function test_google_link_cannot_be_removed_if_it_is_only_login_method_and_password_not_set(): void
    {
        $user = User::factory()->create([
            'google_id' => 'google-1',
            'password_set_at' => null,
            'github_id' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('settings.google.unlink'));

        $response->assertRedirect(route('security.edit'));
        $response->assertSessionHas('error', 'Set a password before disconnecting Google. It is currently your only sign-in method.');

        $this->assertSame('google-1', $user->refresh()->google_id);
    }

    public function test_github_link_can_be_removed_when_another_oauth_login_exists_without_password(): void
    {
        $user = User::factory()->create([
            'google_id' => 'google-1',
            'github_id' => 'github-1',
            'github_avatar' => 'https://example.com/github.png',
            'password_set_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('settings.github.unlink'));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $user->refresh();

        $this->assertNull($user->github_id);
        $this->assertNull($user->github_avatar);
        $this->assertSame('google-1', $user->google_id);
    }
}
