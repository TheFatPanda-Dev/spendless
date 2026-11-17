<?php

namespace App\Jobs;

use App\Models\BankConnection;
use App\Services\EnableBanking\EnableBankingSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncEnableBankingConnectionJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Failures are recorded on the connection and retried at the next slot. */
    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    /**
     * @param  array<string, string>  $psuHeaders  Present when the user started the sync.
     */
    public function __construct(
        public int $bankConnectionId,
        public string $trigger = 'scheduled',
        public array $psuHeaders = [],
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->bankConnectionId;
    }

    public function handle(EnableBankingSync $sync): void
    {
        $connection = BankConnection::query()->find($this->bankConnectionId);

        if (
            $connection === null
            || $connection->provider !== BankConnection::PROVIDER_ENABLE_BANKING
            || in_array($connection->status, [BankConnection::STATUS_PENDING, BankConnection::STATUS_NEEDS_REAUTH], true)
            || $connection->session_id === null
        ) {
            return;
        }

        $sync->sync($connection, $this->trigger, $this->psuHeaders);
    }
}
