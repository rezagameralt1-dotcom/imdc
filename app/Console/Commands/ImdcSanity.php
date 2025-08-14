<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;

class ImdcSanity extends Command
{
    protected $signature = 'imdc:sanity {--json : Output JSON}';
    protected $description = 'Run quick sanity checks for IMDC stack (DB, storage, env, routes)';

    public function handle(): int
    {
        $report = [
            'app' => [
                'name' => Config::get('app.name'),
                'env'  => Config::get('app.env'),
                'debug'=> (bool) Config::get('app.debug'),
                'url'  => Config::get('app.url'),
            ],
            'php' => PHP_VERSION,
            'storage' => [
                'writable_storage' => is_writable(storage_path()) ?: false,
                'writable_cache'   => is_writable(base_path('bootstrap/cache')) ?: false,
            ],
            'db' => [
                'connected' => false,
                'driver' => Config::get('database.default'),
                'database' => env('DB_DATABASE'),
            ],
            'migrations' => [
                'table_exists' => false,
                'pending' => null,
            ],
            'routes' => [
                'api_count' => 0,
            ],
            'queue' => [
                'default' => Config::get('queue.default'),
                'connection' => Config::get('queue.connections.'.Config::get('queue.default').'.driver'),
            ],
            'mail' => [
                'mailer' => Config::get('mail.default'),
                'host'   => Config::get('mail.mailers.smtp.host'),
                'port'   => Config::get('mail.mailers.smtp.port'),
                'from'   => Config::get('mail.from.address'),
            ],
        ];

        // DB + migrations
        try {
            DB::connection()->getPdo();
            $report['db']['connected'] = true;
            $schema = DB::getSchemaBuilder();
            $report['migrations']['table_exists'] = $schema->hasTable('migrations');
            if ($report['migrations']['table_exists']) {
                // crude pending count (migrations not in table but present on disk)
                $applied = DB::table('migrations')->pluck('migration')->all();
                $files = glob(database_path('migrations/*.php')) ?: [];
                $all = array_map(fn($p) => pathinfo($p, PATHINFO_FILENAME), $files);
                $report['migrations']['pending'] = max(0, count(array_diff($all, $applied)));
            }
        } catch (\Throwable $e) {
            $report['db']['connected'] = false;
            $report['db']['error'] = $e->getMessage();
        }

        // Routes count (API only)
        try {
            $api = app('router')->getRoutes()->getRoutesByMethod();
            $count = 0;
            foreach ($api as $method => $routes) {
                foreach ($routes as $r) {
                    $uri = $r->uri();
                    if (str_starts_with($uri, 'api/')) $count++;
                }
            }
            $report['routes']['api_count'] = $count;
        } catch (\Throwable $e) {
            $report['routes']['error'] = $e->getMessage();
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        } else {
            $this->info('IMDC Sanity Report');
            $this->line('App: ' . $report['app']['name'] . ' (' . $report['app']['env'] . ')');
            $this->line('DB connected: ' . ($report['db']['connected'] ? 'yes' : 'no'));
            $this->line('Migrations table: ' . ($report['migrations']['table_exists'] ? 'yes' : 'no') . ', pending: ' . ($report['migrations']['pending'] ?? 'n/a'));
            $this->line('API routes: ' . $report['routes']['api_count']);
            $this->line('Storage writable: ' . (($report['storage']['writable_storage'] && $report['storage']['writable_cache']) ? 'yes' : 'no'));
            $this->line('Queue: ' . $report['queue']['default'] . ' (' . ($report['queue']['connection'] ?? 'n/a') . ')');
            $this->line('Mail: ' . $report['mail']['mailer'] . ' @ ' . ($report['mail']['host'] ?? 'n/a'));
        }

        return 0;
    }
}
