<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Notifications\ConfirmEmailChangeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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

        $response->assertOk();
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
        $user = User::factory()->create();

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
}
