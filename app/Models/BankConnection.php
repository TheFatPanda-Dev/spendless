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
        'user_id',
        'aspsp_name',
        'aspsp_country',
        'state',
        'status',
        'session_id',
        'consent_valid_until',
        'authorized_at',
        'last_synced_at',
        'next_sync_at',
        'last_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'consent_valid_until' => 'datetime',
            'authorized_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'next_sync_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }
}
