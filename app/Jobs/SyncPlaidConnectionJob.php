<?php

namespace App\Jobs;

use App\Models\BankConnection;
use App\Services\Plaid\PlaidTransactionsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SyncPlaidConnectionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $bankConnectionId,
        public string $reason = 'manual_single',
        public bool $triggerRefresh = false,
    ) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('plaid-sync-'.$this->bankConnectionId))
                ->releaseAfter(60)
                ->expireAfter(1800),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(PlaidTransactionsService $transactionsService): void
    {
        $connection = BankConnection::query()->find($this->bankConnectionId);

        if (! $connection || $connection->provider !== 'plaid') {
            return;
        }

        if ($this->triggerRefresh) {
            $transactionsService->refreshTransactions($connection);
        }

        $transactionsService->syncItem($connection, $this->reason);
    }
}
