<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class ImdcBackup extends Command
{
    protected $signature = 'imdc:backup {--compress : gzip the SQL dump}';

    protected $description = 'Create PostgreSQL database backup and rotate old backups/logs';

    public function handle(): int
    {
        // Prepare paths & settings
        $backupDir = config('backup.dir');
        $retention = (int) config('backup.retention_days');
        $logRetention = (int) config('backup.log_retention_days');

        File::ensureDirectoryExists($backupDir);

        $db = [
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (string) env('DB_PORT', 5432),
            'name' => env('DB_DATABASE', 'postgres'),
            'user' => env('DB_USERNAME', 'postgres'),
            'pass' => env('DB_PASSWORD', ''),
        ];

        $timestamp = now()->format('Ymd_His');
        $baseName = "imdc_{$db['name']}_{$timestamp}.sql";
        $dumpPath = rtrim($backupDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$baseName;

        // Build pg_dump command
        $args = [
            'pg_dump',
            '-h', $db['host'],
            '-p', $db['port'],
            '-U', $db['user'],
            '-d', $db['name'],
            '-F', 'p',         // plain SQL
            '-v',              // verbose
            '--no-privileges',
            '--no-owner',
        ];

        $this->info("Backing up database '{$db['name']}' to: {$dumpPath}");

        $process = new Process($args, null, [
            'PGPASSWORD' => $db['pass'],
        ], null, 3600);

        // Stream output to file
        $fp = fopen($dumpPath, 'w');
        if (! $fp) {
            $this->error("Cannot open path for writing: {$dumpPath}");

            return self::FAILURE;
        }
        $process->run(function ($type, $buffer) use ($fp) {
            fwrite($fp, $buffer);
        });
        fclose($fp);

        if (! $process->isSuccessful()) {
            $this->error('pg_dump failed. Check that PostgreSQL client tools are installed and credentials are valid.');
            $this->line($process->getErrorOutput());
            // Remove partial file
            if (File::exists($dumpPath)) {
                File::delete($dumpPath);
            }

            return self::FAILURE;
        }

        // Optional compression
        if ($this->option('compress')) {
            $gzPath = $dumpPath.'.gz';
            $this->info("Compressing to: {$gzPath}");
            $gz = gzopen($gzPath, 'w9');
            $in = fopen($dumpPath, 'rb');
            while (! feof($in)) {
                gzwrite($gz, fread($in, 1024 * 512));
            }
            fclose($in);
            gzclose($gz);
            File::delete($dumpPath);
            $dumpPath = $gzPath;
        }

        $this->comment("Backup created: {$dumpPath}");

        // Rotate old backups
        if ($retention > 0) {
            $cutoff = now()->subDays($retention);
            $deleted = 0;
            foreach (File::files($backupDir) as $file) {
                if ($file->getExtension() !== 'sql' && $file->getExtension() !== 'gz') {
                    continue;
                }
                if ($file->getMTime() < $cutoff->getTimestamp()) {
                    File::delete($file->getRealPath());
                    $deleted++;
                }
            }
            $this->info("Backup rotation: deleted {$deleted} file(s) older than {$retention} day(s).");
        }

        // Rotate laravel logs
        $logsDir = storage_path('logs');
        if ($logRetention > 0 && File::isDirectory($logsDir)) {
            $cutoff = now()->subDays($logRetention)->getTimestamp();
            $deleted = 0;
            foreach (File::files($logsDir) as $f) {
                if ($f->getMTime() < $cutoff) {
                    File::delete($f->getRealPath());
                    $deleted++;
                }
            }
            $this->info("Log rotation: deleted {$deleted} log file(s) older than {$logRetention} day(s).");
        }

        $this->info('Backup finished successfully.');

        return self::SUCCESS;
    }
}
