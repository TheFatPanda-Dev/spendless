<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_connection_id',
        'external_uid',
        'plaid_account_id',
        'name',
        'display_name',
        'official_name',
        'mask_encrypted',
        'type',
        'subtype',
        'iban',
        'currency',
        'currency_code',
        'is_active',
        'balances_encrypted',
        'raw_payload',
        'raw_encrypted',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'mask_encrypted' => 'encrypted',
            'balances_encrypted' => 'encrypted:array',
            'raw_encrypted' => 'encrypted:array',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class, 'bank_connection_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }
}
