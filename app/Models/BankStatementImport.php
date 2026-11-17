<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementImport extends Model
{
    protected $fillable = [
        'bank_account_id',
        'user_id',
        'original_filename',
        'format',
        'rows_total',
        'rows_added',
        'rows_updated',
        'rows_removed',
        'rows_skipped',
        'date_from',
        'date_to',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummaryArray(): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->original_filename,
            'format' => $this->format,
            'rows_added' => $this->rows_added,
            'rows_updated' => $this->rows_updated,
            'rows_removed' => $this->rows_removed,
            'date_from' => $this->date_from?->toDateString(),
            'date_to' => $this->date_to?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
            // Time to download a new statement.
            'is_stale' => $this->created_at === null || $this->created_at->isBefore(now()->subDays(21)),
        ];
    }
}
