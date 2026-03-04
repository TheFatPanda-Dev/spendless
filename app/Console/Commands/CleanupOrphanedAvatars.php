<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOrphanedAvatars extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'avatars:cleanup-orphans {--dry-run : List orphaned files without deleting them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove files in storage/app/public/avatars that are not referenced by any user avatar_path';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $usedAvatarPaths = User::query()
            ->whereNotNull('avatar_path')
            ->pluck('avatar_path')
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->map(fn (string $path): string => ltrim($path, '/'))
            ->unique()
            ->values()
            ->all();

        $storedAvatarPaths = collect(Storage::disk('public')->files('avatars'))
            ->map(fn (string $path): string => ltrim($path, '/'))
            ->all();

        $orphanedPaths = array_values(array_diff($storedAvatarPaths, $usedAvatarPaths));

        if ($orphanedPaths === []) {
            $this->info('No orphaned avatar files found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn(sprintf('Found %d orphaned avatar file(s):', count($orphanedPaths)));

            foreach ($orphanedPaths as $path) {
                $this->line($path);
            }

            return self::SUCCESS;
        }

        Storage::disk('public')->delete($orphanedPaths);

        $this->info(sprintf('Deleted %d orphaned avatar file(s).', count($orphanedPaths)));

        return self::SUCCESS;
    }
}
