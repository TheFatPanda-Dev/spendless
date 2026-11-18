<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupDatabaseCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        // A throwaway folder, so running the tests never touches real local backups.
        $this->directory = sys_get_temp_dir().'/spendless-backup-test-'.uniqid();
        config(['database.backup_path' => $this->directory]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_sqlite_database_is_copied_and_old_backups_are_pruned(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'db');
        file_put_contents($database, 'SQLite format 3');
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database]);

        File::ensureDirectoryExists($this->directory);
        foreach (['20250101-000000', '20250102-000000', '20250103-000000'] as $stamp) {
            file_put_contents($this->directory.'/spendless-'.$stamp.'.sqlite', 'old');
        }

        $this->artisan('spendless:backup-database', ['--keep' => 2])->assertSuccessful();

        $files = glob($this->directory.'/spendless-*') ?: [];
        $this->assertCount(2, $files);
        $this->assertSame('SQLite format 3', file_get_contents(max($files)), 'The newest file is the new backup');
    }

    public function test_it_fails_loudly_when_nothing_can_be_backed_up(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);

        $this->artisan('spendless:backup-database')->assertFailed();
    }
}
