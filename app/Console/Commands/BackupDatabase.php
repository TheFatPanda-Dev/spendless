<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Dumps the database to storage/app/backups (gzipped) and keeps the newest few.
 * The deploy workflow runs this before every migration.
 */
class BackupDatabase extends Command
{
    protected $signature = 'spendless:backup-database {--keep=10 : How many backups to keep}';

    protected $description = 'Back up the database to storage/app/backups';

    public function handle(): int
    {
        $connection = (string) config('database.default');
        $config = (array) config('database.connections.'.$connection);
        $directory = (string) config('database.backup_path', storage_path('app/backups'));
        File::ensureDirectoryExists($directory, 0700);
        $stamp = now()->format('Ymd-His');

        $driver = $config['driver'] ?? null;

        if (! in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            $this->error('Backups are only supported for MySQL/MariaDB and SQLite.');

            return self::FAILURE;
        }

        $path = $driver === 'sqlite'
            ? $this->copySqlite($config, $directory.'/spendless-'.$stamp.'.sqlite')
            : $this->dumpMysql($config, $directory.'/spendless-'.$stamp.'.sql.gz');

        if ($path === null) {
            $this->error('The backup could not be created.');

            return self::FAILURE;
        }

        if (! is_file($path) || filesize($path) === 0) {
            $this->error('The backup file is empty.');

            return self::FAILURE;
        }

        $this->prune($directory, max(1, (int) $this->option('keep')));
        $this->info('Backup written to '.$path.' ('.number_format(filesize($path) / 1024, 1).' KB)');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function dumpMysql(array $config, string $path): ?string
    {
        $command = [(string) config('database.dump_binary', 'mysqldump'), '--single-transaction', '--quick', '--no-tablespaces', '--routines',
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? 3306),
            '--user='.($config['username'] ?? ''),
            (string) ($config['database'] ?? ''),
        ];

        if (! empty($config['unix_socket'])) {
            $command[] = '--socket='.$config['unix_socket'];
        }

        // The password goes through the environment, so it never shows in the process list.
        $process = new Process($command, null, ['MYSQL_PWD' => (string) ($config['password'] ?? '')], null, 600);
        $gzip = gzopen($path, 'wb9');

        if ($gzip === false) {
            return null;
        }

        $process->run(function (string $type, string $buffer) use ($gzip): void {
            if ($type === Process::OUT) {
                gzwrite($gzip, $buffer);
            }
        });

        gzclose($gzip);

        if (! $process->isSuccessful()) {
            @unlink($path);
            $this->error('mysqldump failed: '.trim($process->getErrorOutput()));

            return null;
        }

        @chmod($path, 0600);

        return $path;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function copySqlite(array $config, string $path): ?string
    {
        $database = (string) ($config['database'] ?? '');

        if ($database === '' || $database === ':memory:' || ! is_file($database) || ! copy($database, $path)) {
            return null;
        }

        @chmod($path, 0600);

        return $path;
    }

    private function prune(string $directory, int $keep): void
    {
        $files = glob($directory.'/spendless-*') ?: [];
        rsort($files);

        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
        }
    }
}
