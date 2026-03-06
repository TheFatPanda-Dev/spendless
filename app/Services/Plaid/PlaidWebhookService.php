<?php

namespace App\Services\Plaid;

use App\Jobs\SyncPlaidConnectionJob;
use App\Models\BankConnection;
use App\Models\PlaidWebhookEvent;
use Illuminate\Support\Arr;
use RuntimeException;

class PlaidWebhookService
{
    public function verifySecret(?string $providedSecret): void
    {
        $configuredSecret = (string) config('services.plaid.webhook_secret', '');

        if ($configuredSecret === '') {
            return;
        }

        if ($providedSecret !== $configuredSecret) {
            throw new RuntimeException('Invalid webhook secret.');
        }
    }

    public function handleWebhook(array $payload): void
    {
        $itemId = (string) Arr::get($payload, 'item_id', '');
        $itemHash = $itemId !== '' ? hash('sha256', $itemId) : null;

        $connection = null;

        if ($itemHash !== null) {
            $connection = BankConnection::query()
                ->where('provider', 'plaid')
                ->where('plaid_item_id_hash', $itemHash)
                ->first();
        }

        PlaidWebhookEvent::create([
            'plaid_item_id_hash' => $itemHash,
            'bank_connection_id' => $connection?->id,
            'webhook_type' => Arr::get($payload, 'webhook_type'),
            'webhook_code' => Arr::get($payload, 'webhook_code'),
            'payload_encrypted' => $payload,
            'received_at' => now(),
        ]);

        if (! $connection) {
            return;
        }

        $connection->update([
            'last_webhook_at' => now(),
        ]);

        $webhookType = (string) Arr::get($payload, 'webhook_type', '');
        $webhookCode = (string) Arr::get($payload, 'webhook_code', '');

        if ($webhookType !== 'TRANSACTIONS') {
            return;
        }

        $codesToSync = [
            'SYNC_UPDATES_AVAILABLE',
            'INITIAL_UPDATE',
            'HISTORICAL_UPDATE',
            'DEFAULT_UPDATE',
            'TRANSACTIONS_REMOVED',
        ];

        if (! in_array($webhookCode, $codesToSync, true)) {
            return;
        }

        SyncPlaidConnectionJob::dispatch($connection->id, 'webhook', false);
    }
}
