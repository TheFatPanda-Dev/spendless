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
        'identification_hash',
        'name',
        'display_name',
        'official_name',
        'mask_encrypted',
        'type',
        'subtype',
        'currency',
        'is_active',
        'balances_encrypted',
        'raw_encrypted',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
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

    public function statementImports(): HasMany
    {
        return $this->hasMany(BankStatementImport::class);
    }

    public function investmentPositions(): HasMany
    {
        return $this->hasMany(InvestmentPosition::class);
    }

    public function investmentActivities(): HasMany
    {
        return $this->hasMany(InvestmentActivity::class);
    }

    /**
     * Last four characters of the account number, padded for display ("**1234").
     */
    public function maskForDisplay(): ?string
    {
        $mask = (string) $this->mask_encrypted;

        return $mask === '' ? null : str_pad(substr($mask, -4), 4, '*', STR_PAD_LEFT);
    }
}
