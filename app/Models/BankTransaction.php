<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_connection_id',
        'bank_account_id',
        'category_id',
        'category_manually_set',
        'external_uid',
        'plaid_transaction_id',
        'plaid_pending_transaction_id',
        'account_owner',
        'merchant_name',
        'payee',
        'name',
        'booked_at',
        'amount',
        'currency',
        'iso_currency_code',
        'unofficial_currency_code',
        'date',
        'authorized_date',
        'pending',
        'category',
        'personal_finance_category',
        'payment_channel',
        'location_encrypted',
        'description',
        'raw_payload',
        'raw_encrypted',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'booked_at' => 'date',
            'date' => 'date',
            'authorized_date' => 'date',
            'amount' => 'decimal:2',
            'pending' => 'boolean',
            'category_manually_set' => 'boolean',
            'category' => 'array',
            'personal_finance_category' => 'array',
            'location_encrypted' => 'encrypted:array',
            'raw_payload' => 'array',
            'raw_encrypted' => 'encrypted:array',
            'removed_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class, 'bank_connection_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function assignedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
