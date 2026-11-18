<?php

namespace App\Console\Commands;

use App\Jobs\SyncEnableBankingConnectionJob;
use App\Jobs\SyncIbkrConnectionJob;
use App\Jobs\SyncPlaidConnectionJob;
use App\Models\BankConnection;
use App\Services\Plaid\PlaidUsage;
use Illuminate\Console\Command;

class DispatchDueBankSyncs extends Command
{
    protected $signature = 'banking:dispatch-due-syncs';

    protected $description = 'Queue background syncs for bank connections whose next sync slot has arrived';

    public function handle(): int
    {
        // Authorisations the user never finished at their bank.
        BankConnection::query()
            ->where('status', BankConnection::STATUS_PENDING)
            ->where('created_at', '<', now()->subHour())
            ->delete();

        // A sync that died mid-way (e.g. PHP was killed) must not stay "syncing" forever.
        BankConnection::query()
            ->whereIn('provider', BankConnection::SYNCED_PROVIDERS)
            ->where('status', BankConnection::STATUS_SYNCING)
            ->where('updated_at', '<', now()->subMinutes(30))
            ->update(['status' => BankConnection::STATUS_SYNC_FAILED, 'last_sync_error' => 'The last sync did not finish.']);

        BankConnection::query()
            ->where('provider', BankConnection::PROVIDER_ENABLE_BANKING)
            ->whereIn('status', [BankConnection::STATUS_CONNECTED, BankConnection::STATUS_SYNC_FAILED])
            ->whereNotNull('consent_valid_until')
            ->where('consent_valid_until', '<=', now())
            ->update([
                'status' => BankConnection::STATUS_NEEDS_REAUTH,
                'next_sync_at' => null,
                'last_sync_error' => 'Bank access has expired. Reconnect the bank to keep syncing.',
            ]);

        $dispatched = 0;

        BankConnection::query()
            ->where('provider', BankConnection::PROVIDER_ENABLE_BANKING)
            ->whereIn('status', [BankConnection::STATUS_CONNECTED, BankConnection::STATUS_SYNC_FAILED])
            ->whereNotNull('session_id')
            ->where(fn ($query) => $query->whereNull('next_sync_at')->orWhere('next_sync_at', '<=', now()))
            ->orderBy('next_sync_at')
            ->pluck('id')
            ->each(function (int $connectionId) use (&$dispatched): void {
                SyncEnableBankingConnectionJob::dispatch($connectionId, 'scheduled');
                $dispatched++;
            });

        BankConnection::query()
            ->where('provider', BankConnection::PROVIDER_IBKR)
            ->whereIn('status', [BankConnection::STATUS_CONNECTED, BankConnection::STATUS_SYNC_FAILED])
            ->where(fn ($query) => $query->whereNull('next_sync_at')->orWhere('next_sync_at', '<=', now()))
            ->pluck('id')
            ->each(function (int $connectionId) use (&$dispatched): void {
                SyncIbkrConnectionJob::dispatch($connectionId, 'scheduled');
                $dispatched++;
            });

        // UK banks: once a day, and not at all once the free Plaid allowance is used up.
        if (! PlaidUsage::exhausted()) {
            BankConnection::query()
                ->where('provider', BankConnection::PROVIDER_PLAID)
                ->whereIn('status', [BankConnection::STATUS_CONNECTED, BankConnection::STATUS_SYNC_FAILED])
                ->whereNotNull('next_sync_at')
                ->where('next_sync_at', '<=', now())
                ->pluck('id')
                ->each(function (int $connectionId) use (&$dispatched): void {
                    SyncPlaidConnectionJob::dispatch($connectionId, 'scheduled');
                    $dispatched++;
                });
        }

        $this->info(sprintf('Queued %d bank sync(s).', $dispatched));

        return self::SUCCESS;
    }
}
