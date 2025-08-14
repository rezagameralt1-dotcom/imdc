<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Enable scheduled backup if BACKUP_SCHEDULE_ENABLE=true
        if (env('BACKUP_SCHEDULE_ENABLE', false)) {
            // Daily at 03:00 local time, with compression
            $schedule->command('imdc:backup --compress')->dailyAt(env('BACKUP_SCHEDULE_AT', '03:00'));
        }
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
