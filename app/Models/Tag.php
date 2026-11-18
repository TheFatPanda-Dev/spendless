<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Which car, flat, project… a transaction was for. Categories say what it was.
 */
class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'color'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(BankTransaction::class, 'bank_transaction_tag');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(TagRule::class)->orderBy('id');
    }
}
