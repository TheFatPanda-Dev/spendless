<?php

use App\Jobs\SyncEnableBankingConnectionJob;
use App\Models\BankConnection;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    BankConnection::query()
        ->where('status', 'active')
        ->where(function ($query): void {
            $query->whereNull('next_sync_at')
                ->orWhere('next_sync_at', '<=', now());
        })
        ->orderBy('next_sync_at')
        ->limit(25)
        ->pluck('id')
        ->each(fn (int $connectionId) => SyncEnableBankingConnectionJob::dispatch($connectionId));
})->everyFiveMinutes();
