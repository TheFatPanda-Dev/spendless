<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Read-only view of Laravel's failed_jobs table, for the admin panel.
 */
class FailedJob extends Model
{
    protected $table = 'failed_jobs';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
        ];
    }

    public function jobName(): string
    {
        $payload = json_decode((string) $this->payload, true);

        return class_basename((string) ($payload['displayName'] ?? 'Job'));
    }

    public function errorSummary(): string
    {
        return Str::limit(Str::before((string) $this->exception, "\n"), 300);
    }
}
