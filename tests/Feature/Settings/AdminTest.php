<?php

namespace Tests\Feature\Settings;

use App\Actions\Fortify\CreateUserFromOAuthCandidate;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_open_the_admin_area(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('settings.admin.edit'))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->get(route('settings.admin.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/admin')
                ->has('checks')
                ->has('users', 2)
                ->missing('bankChecks'));
    }

    public function test_setup_check_reports_whether_the_cron_job_runs(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('settings.admin.edit'))
            ->assertInertia(fn (Assert $page) => $page->where(
                'checks',
                fn ($checks): bool => collect($checks)->firstWhere('key', 'cron')['status'] === 'fail',
            ));

        Cache::forever('scheduler:last_run_at', now()->toIso8601String());

        $this->actingAs($admin)
            ->get(route('settings.admin.edit'))
            ->assertInertia(fn (Assert $page) => $page->where(
                'checks',
                fn ($checks): bool => collect($checks)->firstWhere('key', 'cron')['status'] === 'ok',
            ));
    }

    public function test_closing_sign_ups_blocks_email_and_oauth_registration(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('settings.admin.registration'), ['open' => false])
            ->assertSessionHas('success');

        $this->assertFalse(AppSetting::registrationOpen());

        auth()->logout();

        $this->get('/register')->assertRedirect(route('login'));

        $this->post('/register', [
            'name' => 'Stranger',
            'email' => 'stranger@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'stranger@example.com']);

        $this->expectException(InvalidArgumentException::class);
        app(CreateUserFromOAuthCandidate::class)->create([
            'provider' => 'google',
            'provider_label' => 'Google',
            'provider_id' => 'g-123',
            'email' => 'stranger@example.com',
            'name' => 'Stranger',
        ]);
    }

    public function test_admin_can_promote_and_delete_other_users_but_not_themselves(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('settings.admin.users.update', $member), ['is_admin' => true])
            ->assertSessionHas('success');
        $this->assertTrue($member->fresh()->is_admin);

        $this->actingAs($admin)
            ->patch(route('settings.admin.users.update', $admin), ['is_admin' => false])
            ->assertStatus(422);
        $this->assertTrue($admin->fresh()->is_admin);

        $this->actingAs($admin)
            ->delete(route('settings.admin.users.destroy', $admin))
            ->assertStatus(422);

        $this->actingAs($admin)
            ->delete(route('settings.admin.users.destroy', $member))
            ->assertSessionHas('success');
        $this->assertModelMissing($member);
    }

    public function test_make_admin_command(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('spendless:make-admin', ['email' => 'owner@example.com'])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_admin);

        $this->artisan('spendless:make-admin', ['email' => 'owner@example.com', '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->is_admin);

        $this->artisan('spendless:make-admin', ['email' => 'nobody@example.com'])->assertFailed();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }
}
