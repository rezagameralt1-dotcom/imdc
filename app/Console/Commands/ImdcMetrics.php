<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Outputs Prometheus text-format metrics to STDOUT.
 * Use with Node Exporter textfile collector or redirect to a file via cron.
 */
class ImdcMetrics extends Command
{
    protected $signature = 'imdc:metrics {--output= : If set, write to this path}';
    protected $description = 'Emit Prometheus metrics (text format)';

    public function handle(): int
    {
        $lines = [];

        $lines[] = '# HELP imdc_app_info Basic app info labelset';
        $lines[] = '# TYPE imdc_app_info gauge';
        $lines[] = sprintf('imdc_app_info{app="%s",env="%s"} 1', config('app.name'), config('app.env'));

        $lines[] = '# HELP imdc_db_ready Database reachable';
        $lines[] = '# TYPE imdc_db_ready gauge';
        $dbOk = 0;
        try {
            DB::connection()->getPdo();
            $dbOk = 1;
        } catch (\Throwable $e) {
            $dbOk = 0;
        }
        $lines[] = "imdc_db_ready {$dbOk}";

        $lines[] = '# HELP imdc_db_table_rows Row counts of key tables';
        $lines[] = '# TYPE imdc_db_table_rows gauge';
        foreach (['users','orders','products'] as $t) {
            $count = (Schema::hasTable($t)) ? (int) DB::table($t)->count() : 0;
            $lines[] = sprintf('imdc_db_table_rows{table="%s"} %d', $t, $count);
        }

        $text = implode("\n", $lines) . "\n";

        if ($path = $this->option('output')) {
            file_put_contents($path, $text);
            $this->info("Wrote metrics to: {$path}");
        } else {
            $this->line($text);
        }
        return self::SUCCESS;
    }
}
