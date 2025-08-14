<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AdminActivityLog;
use Carbon\Carbon;

class PruneAdminActivity extends Command
{
    protected $signature = 'digitalcity:prune-activity {--days=90 : Remove logs older than N days}';
    protected $description = 'Prune old admin activity logs';

    public function handle(): int
    {
        $days = (int)$this->option('days');
        $cut  = Carbon::now()->subDays($days);
        $count = AdminActivityLog::where('created_at', '<', $cut)->delete();
        $this->info("Pruned {$count} admin activity logs older than {$days} days.");
        return self::SUCCESS;
    }
}

