<?php

namespace App\Jobs;

use App\Models\BankConnection;
use App\Services\Ibkr\IbkrSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncIbkrConnectionJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Waiting for IBKR to generate the report can take most of a minute. */
    public int $timeout = 240;

    public int $uniqueFor = 600;

    public function __construct(public int $bankConnectionId, public string $trigger = 'scheduled') {}

    public function uniqueId(): string
    {
        return 'ibkr-'.$this->bankConnectionId;
    }

    public function handle(IbkrSync $sync): void
    {
        $connection = BankConnection::query()->with(['user', 'wallet'])->find($this->bankConnectionId);

        if ($connection === null || $connection->provider !== BankConnection::PROVIDER_IBKR || $connection->status === BankConnection::STATUS_NEEDS_REAUTH) {
            return;
        }

        $sync->sync($connection, $this->trigger);
    }
}
