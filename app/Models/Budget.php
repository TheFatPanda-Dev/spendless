<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A monthly budget in the user's base currency: the total when category_id is null,
 * otherwise for one main category.
 */
class Budget extends Model
{
    protected $fillable = ['user_id', 'category_id', 'amount'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'category_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
