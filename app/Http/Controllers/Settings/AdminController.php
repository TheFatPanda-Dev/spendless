<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Banking\EnableBankingConnectionController;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\BankConnection;
use App\Models\User;
use App\Services\EnableBanking\EnableBankingClient;
use App\Support\Admin\SetupChecks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function edit(Request $request, SetupChecks $setupChecks): Response
    {
        $users = User::query()
            ->withCount([
                'wallets',
                'bankConnections as banks_count' => fn ($query) => $query
                    ->where('provider', BankConnection::PROVIDER_ENABLE_BANKING)
                    ->where('status', '!=', BankConnection::STATUS_PENDING),
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->display_name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'email_verified' => $user->email_verified_at !== null,
                'two_factor' => $user->two_factor_confirmed_at !== null,
                'wallets_count' => $user->wallets_count,
                'banks_count' => $user->banks_count,
                'created_at' => $user->created_at?->toIso8601String(),
                'is_you' => $user->id === $request->user()->id,
            ]);

        $connections = BankConnection::query()
            ->with('user:id,name,email')
            ->withCount('accounts')
            ->whereIn('provider', [BankConnection::PROVIDER_ENABLE_BANKING, BankConnection::PROVIDER_IBKR])
            ->where('status', '!=', BankConnection::STATUS_PENDING)
            ->orderBy('institution_name')
            ->get()
            ->map(fn (BankConnection $connection): array => [
                ...$connection->toStatusArray(),
                'owner' => $connection->user?->email,
                'accounts_count' => $connection->accounts_count,
            ]);

        $failedJobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(20)
            ->get(['id', 'payload', 'exception', 'failed_at'])
            ->map(fn (object $job): array => [
                'id' => $job->id,
                'job' => class_basename((string) (json_decode((string) $job->payload, true)['displayName'] ?? 'Job')),
                'error' => Str::limit(Str::before((string) $job->exception, "\n"), 300),
                'failed_at' => $job->failed_at,
            ]);

        return Inertia::render('settings/admin', [
            'checks' => $setupChecks->local($request),
            'bankChecks' => Inertia::defer(fn (): array => $setupChecks->enableBanking()),
            'registrationOpen' => AppSetting::registrationOpen(),
            'users' => $users,
            'connections' => $connections,
            'failedJobs' => $failedJobs,
        ]);
    }

    public function updateRegistration(Request $request): RedirectResponse
    {
        $open = $request->validate(['open' => ['required', 'boolean']])['open'];

        AppSetting::putValue(AppSetting::REGISTRATION_OPEN, $open ? '1' : '0');

        return back()->with('success', $open ? 'New sign-ups are open.' : 'New sign-ups are closed.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'You cannot change your own admin role.');

        $isAdmin = $request->validate(['is_admin' => ['required', 'boolean']])['is_admin'];

        $user->forceFill(['is_admin' => $isAdmin])->save();

        return back()->with('success', sprintf('%s is %s an admin.', $user->email, $isAdmin ? 'now' : 'no longer'));
    }

    public function destroyUser(Request $request, User $user, EnableBankingClient $client): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'Delete your own account from your profile settings.');

        $user->bankConnections()
            ->where('provider', BankConnection::PROVIDER_ENABLE_BANKING)
            ->get()
            ->each(fn (BankConnection $connection) => EnableBankingConnectionController::closeBankSession($client, $connection));

        $email = $user->email;
        $user->delete();

        return back()->with('success', sprintf('%s and all their data were deleted.', $email));
    }

    public function retryFailedJobs(): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => ['all']]);

        return back()->with('success', 'Failed jobs were queued again.');
    }

    public function clearFailedJobs(): RedirectResponse
    {
        Artisan::call('queue:flush');

        return back()->with('success', 'Failed jobs were cleared.');
    }
}
