<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentPosition extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'mark_price' => 'float',
            'position_value' => 'float',
            'cost_basis' => 'float',
            'unrealized_pnl' => 'float',
            'fx_rate_to_base' => 'float',
            'value_in_base' => 'float',
            'unrealized_pnl_in_base' => 'float',
            'report_date' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }
}
