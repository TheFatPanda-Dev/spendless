<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetValuation extends Model
{
    protected $fillable = ['bank_account_id', 'date', 'value'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'value' => 'float',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }
}
