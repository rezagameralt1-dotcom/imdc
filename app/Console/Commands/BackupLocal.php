<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BackupLocal extends Command
{
    protected $signature = 'digitalcity:backup:local {--keep=10 : Keep last N backup files}';
    protected $description = 'Create a lightweight zip backup of public storage and key app files (local/dev use).';

    public function handle(): int
    {
        $ts = now()->format('Ymd-His');
        $backupDir = storage_path('backups');
        if (! is_dir($backupDir)) {
            @mkdir($backupDir, 0775, true);
        }
        $zipPath = $backupDir . DIRECTORY_SEPARATOR . "backup-{$ts}.zip";

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error('Cannot create zip at: ' . $zipPath);
            return self::FAILURE;
        }

        // 1) Public storage
        $publicPath = storage_path('app/public');
        if (is_dir($publicPath)) {
            $this->addDirToZip($zip, $publicPath, 'storage_public');
        }

        // 2) .env (if exists)
        $envPath = base_path('.env');
        if (is_file($envPath)) {
            $zip->addFile($envPath, '.env.backup');
        }

        // 3) Database dump placeholder (devs can replace with real dump command in CI)
        $zip->addFromString('README.txt', "DigitalCity local backup - generated at {$ts}\nThis is a lightweight backup.\n");

        $zip->close();
        $this->info("Backup created: {$zipPath}");

        // Keep last N
        $keep = (int) $this->option('keep');
        $files = collect(glob($backupDir . DIRECTORY_SEPARATOR + '*backup-*.zip')).sort();
        if ($files->count() > $keep) {
            foreach ($files->slice(0, $files->count() - $keep) as $f) {
                @unlink($f);
            }
        }

        return self::SUCCESS;
    }

    protected function addDirToZip(ZipArchive $zip, string $path, string $base): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $localName = $base . DIRECTORY_SEPARATOR . ltrim(str_replace($path, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            if ($file->isDir()) {
                $zip->addEmptyDir(str_replace('\\','/',$localName));
            } else {
                $zip->addFile($file->getPathname(), str_replace('\\','/',$localName));
            }
        }
    }
}

