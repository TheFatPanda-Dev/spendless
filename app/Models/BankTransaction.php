<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BankTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_connection_id',
        'bank_account_id',
        'category_id',
        'category_manually_set',
        'external_uid',
        'merchant_name',
        'payee',
        'name',
        'amount',
        'currency',
        'date',
        'pending',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'pending' => 'boolean',
            'category_manually_set' => 'boolean',
            'tag_rules_applied' => 'boolean',
        ];
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'bank_transaction_tag');
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
