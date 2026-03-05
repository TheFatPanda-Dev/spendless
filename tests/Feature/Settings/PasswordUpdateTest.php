<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\PasswordSetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_update_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('user-password.edit'));

        $response->assertOk();
    }

    public function test_password_can_be_updated()
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('user-password.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Password changed successfully.')
            ->assertRedirect(route('user-password.edit'));

        $this->assertTrue(Hash::check('NewPassword1!', $user->refresh()->password));
        Notification::assertSentTo($user, PasswordChangedNotification::class, function (PasswordChangedNotification $notification, array $channels) use ($user): bool {
            $mailMessage = $notification->toMail($user);

            return in_array('mail', $channels, true)
                && $mailMessage->view === 'emails.password-changed';
        });
        Notification::assertNotSentTo($user, PasswordSetNotification::class);
    }

    public function test_new_password_must_not_match_current_password(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('user-password.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $response
            ->assertSessionHasErrors([
                'password' => 'The new password must be different from your current password.',
            ])
            ->assertRedirect(route('user-password.edit'));

        Notification::assertNothingSent();
    }

    public function test_correct_password_must_be_provided_to_update_password()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('user-password.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('user-password.edit'));
    }

    public function test_oauth_user_can_set_password_without_current_password_when_not_yet_set(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'google_id' => 'google-123',
            'password_set_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('user-password.update'), [
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Password changed successfully.')
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword1!', $user->password));
        $this->assertNotNull($user->password_set_at);
        Notification::assertSentTo($user, PasswordSetNotification::class, function (PasswordSetNotification $notification, array $channels) use ($user): bool {
            $mailMessage = $notification->toMail($user);

            return in_array('mail', $channels, true)
                && $mailMessage->view === 'emails.password-set';
        });
        Notification::assertNotSentTo($user, PasswordChangedNotification::class);
    }
}
