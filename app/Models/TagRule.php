<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tag new transactions automatically: from this account, and/or whose payee or description
 * contains this text.
 */
class TagRule extends Model
{
    protected $fillable = ['user_id', 'tag_id', 'bank_account_id', 'contains'];

    protected function casts(): array
    {
        return [
            'tag_id' => 'integer',
            'bank_account_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }
}
