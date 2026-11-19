<?php

namespace Tests\Feature\Admin;

use App\Actions\Fortify\CreateUserFromOAuthCandidate;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\BankConnections\Pages\ListBankConnections;
use App\Filament\Resources\FailedJobs\Pages\ListFailedJobs;
use App\Filament\Resources\SyncRuns\Pages\ListBankSyncRuns;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Widgets\SetupChecksTable;
use App\Models\AppSetting;
use App\Models\BankConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_open_the_admin_panel(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertForbidden();

        $admin = $this->admin();

        foreach (['/admin', '/admin/users', '/admin/bank-connections', '/admin/sync-runs', '/admin/failed-jobs'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_the_old_admin_address_redirects_to_the_panel(): void
    {
        $this->actingAs($this->admin())
            ->get('/settings/admin')
            ->assertRedirect('/admin');
    }

    public function test_setup_checks_report_whether_the_cron_job_runs(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(SetupChecksTable::class)
            ->assertSee('Cron job (background syncing)')
            ->assertSee('Broken');

        Cache::forever('scheduler:last_run_at', now()->toIso8601String());

        $records = Livewire::test(SetupChecksTable::class)->instance()->getTableRecords();
        $this->assertSame('ok', $records['cron']['status']);
    }

    public function test_closing_sign_ups_blocks_email_and_oauth_registration(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(AppSetting::registrationOpen());

        Livewire::test(Dashboard::class)
            ->callAction('toggleRegistration')
            ->assertNotified('New sign-ups are closed.');

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
        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$admin, $member])
            ->assertTableActionHidden('toggleAdmin', $admin)
            ->assertTableActionHidden('deleteUser', $admin)
            ->callTableAction('toggleAdmin', $member)
            ->assertNotified();

        $this->assertTrue($member->fresh()->is_admin);

        Livewire::test(ListUsers::class)
            ->callTableAction('deleteUser', $member)
            ->assertNotified();

        $this->assertModelMissing($member);
        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_bank_connections_and_sync_runs_are_listed(): void
    {
        $owner = User::factory()->create();
        $connection = BankConnection::query()->create([
            'user_id' => $owner->id,
            'provider' => BankConnection::PROVIDER_ENABLE_BANKING,
            'institution_name' => 'NLB',
            'aspsp_name' => 'NLB',
            'aspsp_country' => 'SI',
            'state' => (string) Str::uuid(),
            'status' => BankConnection::STATUS_NEEDS_REAUTH,
        ]);
        $pending = BankConnection::query()->create([
            'user_id' => $owner->id,
            'provider' => BankConnection::PROVIDER_ENABLE_BANKING,
            'institution_name' => 'Half-connected bank',
            'aspsp_name' => 'Half-connected bank',
            'aspsp_country' => 'SI',
            'state' => (string) Str::uuid(),
            'status' => BankConnection::STATUS_PENDING,
        ]);
        $run = $connection->syncRuns()->create([
            'sync_type' => 'scheduled',
            'status' => 'failed',
            'started_at' => now(),
            'error_message' => 'EXPIRED_SESSION',
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ListBankConnections::class)
            ->assertCanSeeTableRecords([$connection])
            ->assertCanNotSeeTableRecords([$pending])
            ->assertSee('Needs reconnecting');

        Livewire::test(ListBankSyncRuns::class)
            ->assertCanSeeTableRecords([$run])
            ->assertSee('EXPIRED_SESSION');
    }

    public function test_failed_jobs_can_be_cleared(): void
    {
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\SyncEnableBankingConnectionJob']),
            'exception' => "RuntimeException: Bank said no\n#0 trace",
            'failed_at' => now(),
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ListFailedJobs::class)
            ->assertSee('SyncEnableBankingConnectionJob')
            ->assertSee('RuntimeException: Bank said no')
            ->callTableAction('clearAll')
            ->assertNotified('Failed jobs were cleared.');

        $this->assertDatabaseCount('failed_jobs', 0);
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
