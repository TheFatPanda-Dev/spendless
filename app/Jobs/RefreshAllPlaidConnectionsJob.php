<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Plaid\PlaidTransactionsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshAllPlaidConnectionsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $userId) {}

    /**
     * Execute the job.
     */
    public function handle(PlaidTransactionsService $transactionsService): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $transactionsService->refreshAllUserConnections($user);
    }
}
