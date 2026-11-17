<?php

namespace App\Console\Commands;

use App\Models\BankConnection;
use App\Notifications\BankAccessExpiringNotification;
use Illuminate\Console\Command;
use Throwable;

class SendBankAccessReminders extends Command
{
    protected $signature = 'banking:send-access-reminders';

    protected $description = 'Email users whose bank access ends within a week';

    public function handle(): int
    {
        $sent = 0;

        BankConnection::query()
            ->with('user')
            ->where('provider', BankConnection::PROVIDER_ENABLE_BANKING)
            ->whereIn('status', [BankConnection::STATUS_CONNECTED, BankConnection::STATUS_SYNC_FAILED, BankConnection::STATUS_SYNCING])
            ->whereNull('consent_reminder_sent_at')
            ->whereBetween('consent_valid_until', [now(), now()->addDays(BankConnection::CONSENT_WARNING_DAYS)])
            ->each(function (BankConnection $connection) use (&$sent): void {
                if ($connection->user === null) {
                    return;
                }

                try {
                    $connection->user->notify(new BankAccessExpiringNotification($connection));
                } catch (Throwable $exception) {
                    report($exception);

                    return;
                }

                $connection->forceFill(['consent_reminder_sent_at' => now()])->save();
                $sent++;
            });

        $this->info(sprintf('Sent %d reminder(s).', $sent));

        return self::SUCCESS;
    }
}
