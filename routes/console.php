<?php

use App\Models\BankSyncRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

/*
| Everything here is driven by ONE cron entry (see docs/DEPLOY-NEOSERV.md):
|   * * * * * cd /path/to/spendless && php artisan schedule:run >> /dev/null 2>&1
*/

// Lets the admin page confirm the cron job is running.
Schedule::call(fn () => Cache::forever('scheduler:last_run_at', now()->toIso8601String()))
    ->name('scheduler-heartbeat')
    ->everyMinute();

Schedule::command('banking:dispatch-due-syncs')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('banking:send-access-reminders')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('assets:refresh')->dailyAt('00:30')->withoutOverlapping();
Schedule::command('model:prune', ['--model' => [BankSyncRun::class]])->daily();
Schedule::command('queue:prune-failed', ['--hours' => 24 * 30])->daily();

// Statement files uploaded but never confirmed.
Schedule::call(function (): void {
    $disk = Storage::disk('local');

    foreach ($disk->allFiles('imports') as $file) {
        if ($disk->lastModified($file) < now()->subDay()->getTimestamp()) {
            $disk->delete($file);
        }
    }
})->name('prune-statement-uploads')->daily();

// Shared hosting can't keep a queue worker running, so start a short-lived one every
// minute. Keep this last: it blocks this schedule:run for up to 50 seconds.
Schedule::command('queue:work', ['--stop-when-empty', '--max-time' => 50, '--tries' => 1])
    ->everyMinute()
    ->withoutOverlapping(5);
