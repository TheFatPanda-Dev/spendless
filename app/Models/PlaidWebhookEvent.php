<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaidWebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'plaid_item_id_hash',
        'bank_connection_id',
        'webhook_type',
        'webhook_code',
        'payload_encrypted',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_encrypted' => 'encrypted:array',
            'received_at' => 'datetime',
        ];
    }

    public function bankConnection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class);
    }
}
