<?php

namespace App\Services\Banking;

use App\Models\BankActivityLog;
use App\Models\User;

class BankActivityLogger
{
    public function log(User $user, string $eventType, array $payload = []): void
    {
        BankActivityLog::query()->create([
            'user_id' => $user->id,
            'event_type' => $eventType,
            'payload' => $payload === [] ? null : $payload,
            'performed_at' => now(),
        ]);
    }
}
