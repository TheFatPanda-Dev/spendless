<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankSyncRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_connection_id',
        'sync_type',
        'status',
        'cursor_before',
        'cursor_after',
        'added_count',
        'modified_count',
        'removed_count',
        'started_at',
        'finished_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function bankConnection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class);
    }
}
