<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_account_id',
        'external_uid',
        'booked_at',
        'amount',
        'currency',
        'description',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'booked_at' => 'date',
            'amount' => 'decimal:2',
            'raw_payload' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }
}
