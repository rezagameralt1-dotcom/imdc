<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Weekly content export (safe app-level export; no external tools required)
        $schedule->command('digitalcity:export --dir=exports/weekly')
            ->weeklyOn(1, '3:00')
            ->onOneServer()
            ->withoutOverlapping();

        // Daily prune of admin activity logs
        $schedule->command('digitalcity:prune-activity --days=90')
            ->dailyAt('02:30')
            ->withoutOverlapping();

        // Housekeeping
        $schedule->command('queue:prune-batches --hours=24')->daily();
        $schedule->command('cache:prune-stale-tags')->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

