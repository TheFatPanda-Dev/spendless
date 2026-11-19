<?php

namespace App\Filament\Widgets;

use App\Models\AppSetting;
use App\Models\BankConnection;
use App\Models\User;
use App\Services\EnableBanking\SyncSchedule;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class AdminStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $users = User::query()->count();
        $admins = User::query()->where('is_admin', true)->count();

        $banks = BankConnection::query()
            ->whereIn('provider', BankConnection::SYNCED_PROVIDERS)
            ->where('status', '!=', BankConnection::STATUS_PENDING);
        $connected = (clone $banks)->count();
        $needsAttention = (clone $banks)
            ->whereIn('status', [BankConnection::STATUS_NEEDS_REAUTH, BankConnection::STATUS_SYNC_FAILED])
            ->count();

        $failedJobs = DB::table('failed_jobs')->count();
        $open = AppSetting::registrationOpen();

        return [
            Stat::make('Users', $users)
                ->description($admins === 1 ? '1 admin' : "{$admins} admins"),
            Stat::make('Bank connections', $connected)
                ->description($needsAttention === 0 ? 'All syncing' : "{$needsAttention} need attention")
                ->color($needsAttention === 0 ? 'success' : 'warning'),
            Stat::make('Failed jobs', $failedJobs)
                ->description($failedJobs === 0 ? 'Queue is healthy' : 'Retry or clear them under Failed jobs')
                ->color($failedJobs === 0 ? 'success' : 'danger'),
            Stat::make('Sign-ups', $open ? 'Open' : 'Closed')
                ->description('Banks sync at '.SyncSchedule::describe()),
        ];
    }
}
