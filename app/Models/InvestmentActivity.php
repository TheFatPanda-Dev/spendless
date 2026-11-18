<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A trade, deposit or withdrawal in an investment account. Not counted as income or spending.
 */
class InvestmentActivity extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quantity' => 'float',
            'price' => 'float',
            'amount' => 'float',
            'commission' => 'float',
            'fx_rate_to_base' => 'float',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }
}
