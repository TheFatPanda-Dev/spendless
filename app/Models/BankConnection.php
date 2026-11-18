<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankConnection extends Model
{
    use HasFactory;

    public const PROVIDER_ENABLE_BANKING = 'enable_banking';

    public const PROVIDER_MANUAL = 'manual';

    /** Kept up to date by importing statement files (banks without an open-banking route). */
    public const PROVIDER_IMPORT = 'import';

    /** Interactive Brokers, read through its Flex Web Service. */
    public const PROVIDER_IBKR = 'ibkr';

    /** Savings and pensions tracked by hand: a fixed-term Cash ISA or a pension value. */
    public const PROVIDER_ASSET = 'asset';

    /** Waiting for the user to approve access at their bank. */
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_SYNCING = 'syncing';

    /** The last sync failed; it is retried at the next slot. */
    public const STATUS_SYNC_FAILED = 'sync_failed';

    /** Bank access expired or was revoked; the user has to reconnect. */
    public const STATUS_NEEDS_REAUTH = 'needs_reauth';

    /** Days before access ends when the app starts asking the user to reconnect. */
    public const CONSENT_WARNING_DAYS = 7;

    protected $fillable = [
        'wallet_id',
        'user_id',
        'provider',
        'institution_name',
        'institution_logo',
        'aspsp_name',
        'aspsp_country',
        'state',
        'status',
        'session_id',
        'consent_valid_until',
        'consent_reminder_sent_at',
        'authorized_at',
        'last_synced_at',
        'next_sync_at',
        'last_sync_error',
        'metadata',
        'credentials_encrypted',
        'sync_failures',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['credentials_encrypted'];

    protected function casts(): array
    {
        return [
            'consent_valid_until' => 'datetime',
            'consent_reminder_sent_at' => 'datetime',
            'authorized_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'next_sync_at' => 'datetime',
            'metadata' => 'array',
            'credentials_encrypted' => 'encrypted:array',
            'sync_failures' => 'integer',
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

    public function isManual(): bool
    {
        return $this->provider === self::PROVIDER_MANUAL;
    }

    public function consentExpiresSoon(): bool
    {
        return $this->consent_valid_until !== null
            && $this->consent_valid_until->isBefore(now()->addDays(self::CONSENT_WARNING_DAYS));
    }

    /**
     * Shape shared by the wallet pages, settings and the admin area.
     *
     * @return array<string, mixed>
     */
    public function toStatusArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'status' => $this->status,
            'institution_name' => $this->institution_name,
            'institution_logo' => $this->institution_logo,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'next_sync_at' => $this->next_sync_at?->toIso8601String(),
            'consent_valid_until' => $this->consent_valid_until?->toIso8601String(),
            'consent_expires_soon' => ! $this->isManual() && $this->consentExpiresSoon(),
            'error_message' => $this->last_sync_error,
        ];
    }
}
