<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'user_id',
        'provider',
        'plaid_item_id_hash',
        'plaid_item_id_encrypted',
        'plaid_access_token_encrypted',
        'institution_id',
        'institution_name',
        'plaid_cursor',
        'aspsp_name',
        'aspsp_country',
        'state',
        'status',
        'session_id',
        'consent_valid_until',
        'authorized_at',
        'last_synced_at',
        'last_webhook_at',
        'next_sync_at',
        'last_sync_error',
        'available_products',
        'error_code',
        'error_message',
        'metadata',
        'sync_failures',
    ];

    protected function casts(): array
    {
        return [
            'plaid_item_id_encrypted' => 'encrypted',
            'plaid_access_token_encrypted' => 'encrypted',
            'consent_valid_until' => 'datetime',
            'authorized_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_webhook_at' => 'datetime',
            'next_sync_at' => 'datetime',
            'available_products' => 'array',
            'metadata' => 'array',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(BankSyncRun::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(PlaidWebhookEvent::class);
    }
}
