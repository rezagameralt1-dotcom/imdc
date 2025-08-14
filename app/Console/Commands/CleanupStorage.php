<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupStorage extends Command
{
    protected $signature = 'digitalcity:storage:cleanup {--days=14 : Delete files older than N days from storage/app/tmp}';

    protected $description = 'Remove old temporary files to keep storage tidy.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cut = now()->subDays($days)->getTimestamp();
        $dir = storage_path('app/tmp');

        if (! is_dir($dir)) {
            $this->info('No tmp directory. Nothing to cleanup.');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach (File::allFiles($dir) as $file) {
            if ($file->getMTime() < $cut) {
                @unlink($file->getRealPath());
                $deleted++;
            }
        }

        $this->info("Cleanup done. Deleted {$deleted} old files.");

        return self::SUCCESS;
    }
}
