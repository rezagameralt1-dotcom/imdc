<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class ImdcBuildRelease extends Command
{
    protected $signature = 'imdc:build-release 
        {--name=imdc-release : Base name for the release}
        {--no-frontend : Skip building frontend (npm)}
        {--no-composer : Skip composer install --no-dev}
        {--zip-only : Only zip current tree (no build steps)}';
    protected $description = 'Build production ZIP (no dev/test), include compiled assets and caches';

    public function handle(): int
    {
        $ts = now()->format('Ymd_His');
        $baseName = $this->option('name') . '-' . $ts;
        $releasesDir = storage_path('releases');
        File::ensureDirectoryExists($releasesDir);

        $workDir = storage_path('app/release_work_' . $ts);
        File::ensureDirectoryExists($workDir);

        $this->info("Preparing working directory: {$workDir}");

        // rsync-like copy with ignore list
        $root = base_path();
        $ignore = [
            '.git', '.github', '.vite', 'node_modules', 'vendor', 'storage/logs',
            'storage/framework/cache', 'storage/framework/testing', 'tests', 'test', '.idea', '.vscode',
            'docker', 'infra', '.env', '.env.local', '.DS_Store',
        ];

        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $file) {
            $path = $file->getPathname();
            $rel = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
            // skip self (storage/release_work_...)
            if (str_starts_with($rel, 'storage' . DIRECTORY_SEPARATOR . 'release_work_')) {
                continue;
            }
            // ignore
            foreach ($ignore as $ig) {
                if (str_starts_with($rel, $ig)) {
                    continue 2;
                }
            }
            $dest = $workDir . DIRECTORY_SEPARATOR . $rel;
            if ($file->isDir()) {
                File::ensureDirectoryExists($dest);
            } else {
                File::ensureDirectoryExists(dirname($dest));
                File::copy($path, $dest);
            }
        }

        // Composer install (prod)
        if (!$this->option('zip-only') && !$this->option('no-composer')) {
            if (self::binExists('composer')) {
                $this->info('Running composer install --no-dev --optimize-autoloader ...');
                $p = Process::fromShellCommandline('composer install --no-dev --optimize-autoloader', $workDir, null, null, 1800);
                $p->run(function ($type, $buffer) { echo $buffer; });
                if (!$p->isSuccessful()) {
                    $this->warn('Composer install failed; continuing with source only.');
                }
            } else {
                $this->warn('Composer not found; skipping composer install.');
            }
        }

        // Frontend build (if present)
        if (!$this->option('zip-only') && !$this->option('no-frontend') && file_exists(base_path('package.json'))) {
            if (self::binExists('npm')) {
                $this->info('Building frontend (npm ci && npm run build) ...');
                $p = Process::fromShellCommandline('npm ci && npm run build', base_path(), null, null, 1800);
                $p->run(function ($type, $buffer) { echo $buffer; });
                if ($p->isSuccessful()) {
                    // Try to copy common dist paths into public/assets
                    $targets = ['dist', 'build'];
                    foreach ($targets as $t) {
                        $src = base_path($t);
                        if (is_dir($src)) {
                            $dst = $workDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets';
                            File::ensureDirectoryExists($dst);
                            self::copyDir($src, $dst);
                            break;
                        }
                    }
                } else {
                    $this->warn('Frontend build failed; continuing without compiled assets.');
                }
            } else {
                $this->warn('npm not found; skipping frontend build.');
            }
        }

        // Optimize caches inside workdir (if artisan exists)
        if (!$this->option('zip-only') && file_exists($workDir . '/artisan')) {
            $this->info('Caching config/routes/views (artisan) ...');
            $cmd = PHP_BINARY . ' artisan config:cache && ' . PHP_BINARY . ' artisan route:cache && ' . PHP_BINARY . ' artisan view:cache';
            $p = Process::fromShellCommandline($cmd, $workDir, null, null, 300);
            $p->run(function ($type, $buffer) { echo $buffer; });
            if (!$p->isSuccessful()) {
                $this->warn('Artisan cache build failed; continuing.');
            }
        }

        // Ensure env example present
        if (!file_exists($workDir . '/.env.example') && file_exists(base_path('.env.example'))) {
            File::copy(base_path('.env.example'), $workDir . '/.env.example');
        }

        // Include docs
        if (file_exists(base_path('docs'))) {
            self::copyDir(base_path('docs'), $workDir . '/docs');
        }

        // Create ZIP
        $zipPath = $releasesDir . DIRECTORY_SEPARATOR . $baseName . '.zip';
        $this->info("Creating ZIP: {$zipPath}");
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->error('Cannot create zip');
            return self::FAILURE;
        }
        $rii2 = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($workDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($rii2 as $file) {
            $filePath = $file->getPathname();
            $rel = ltrim(str_replace($workDir, '', $filePath), DIRECTORY_SEPARATOR);
            if ($file->isDir()) continue;
            $zip->addFile($filePath, $rel);
        }
        $zip->close();

        // Cleanup workdir
        self::rrmdir($workDir);

        $this->info("Release ready: {$zipPath}");
        $this->line("Install guide: docs/IMDC_SETUP_FA.md");
        return self::SUCCESS;
    }

    private static function binExists(string $bin): bool
    {
        $p = Process::fromShellCommandline(
            (PHP_OS_FAMILY === 'Windows') ? "where {$bin}" : "command -v {$bin}",
            null, null, null, 10
        );
        $p->run();
        return $p->isSuccessful();
    }

    private static function copyDir(string $src, string $dst): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $rel = ltrim(str_replace($src, '', $f->getPathname()), DIRECTORY_SEPARATOR);
            $to = $dst . DIRECTORY_SEPARATOR . $rel;
            if ($f->isDir()) {
                File::ensureDirectoryExists($to);
            } else {
                File::ensureDirectoryExists(dirname($to));
                File::copy($f->getPathname(), $to);
            }
        }
    }

    private static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }
}
