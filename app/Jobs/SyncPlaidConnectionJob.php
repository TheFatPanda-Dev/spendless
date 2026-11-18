<?php

namespace App\Jobs;

use App\Models\BankConnection;
use App\Services\Plaid\PlaidSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncPlaidConnectionJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public int $uniqueFor = 600;

    public function __construct(public int $bankConnectionId, public string $trigger = 'scheduled') {}

    public function uniqueId(): string
    {
        return 'plaid-'.$this->bankConnectionId;
    }

    public function handle(PlaidSync $sync): void
    {
        $connection = BankConnection::query()->with(['user', 'wallet'])->find($this->bankConnectionId);

        if ($connection === null || $connection->provider !== BankConnection::PROVIDER_PLAID || $connection->status === BankConnection::STATUS_NEEDS_REAUTH) {
            return;
        }

        $sync->sync($connection, $this->trigger);
    }
}
