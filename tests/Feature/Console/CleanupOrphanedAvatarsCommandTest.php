<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanupOrphanedAvatarsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_lists_orphaned_avatar_files_without_deleting_them(): void
    {
        Storage::fake('public');

        User::factory()->create([
            'avatar_path' => 'avatars/used-avatar.png',
        ]);

        Storage::disk('public')->put('avatars/used-avatar.png', 'used-avatar');
        Storage::disk('public')->put('avatars/orphan-avatar.png', 'orphan-avatar');

        $this->artisan('avatars:cleanup-orphans --dry-run')
            ->expectsOutput('Found 1 orphaned avatar file(s):')
            ->expectsOutput('avatars/orphan-avatar.png')
            ->assertExitCode(0);

        Storage::disk('public')->assertExists('avatars/used-avatar.png');
        Storage::disk('public')->assertExists('avatars/orphan-avatar.png');
    }

    public function test_command_deletes_orphaned_avatar_files_only(): void
    {
        Storage::fake('public');

        User::factory()->create([
            'avatar_path' => 'avatars/used-avatar.png',
        ]);

        Storage::disk('public')->put('avatars/used-avatar.png', 'used-avatar');
        Storage::disk('public')->put('avatars/orphan-avatar.png', 'orphan-avatar');

        $this->artisan('avatars:cleanup-orphans')
            ->expectsOutput('Deleted 1 orphaned avatar file(s).')
            ->assertExitCode(0);

        Storage::disk('public')->assertExists('avatars/used-avatar.png');
        Storage::disk('public')->assertMissing('avatars/orphan-avatar.png');
    }
}
