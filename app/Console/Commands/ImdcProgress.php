<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

class ImdcProgress extends Command
{
    protected $signature = 'imdc:progress {--json : Output JSON}';

    protected $description = 'Audit IMDC project completion against baseline checklist and estimate % completeness';

    public function handle(): int
    {
        $baseline = $this->baseline();
        $results = [];
        $scoreHit = 0;
        $scoreTotal = 0;

        foreach ($baseline as $group => $items) {
            foreach ($items as $item) {
                $scoreTotal += $item['weight'];
                $ok = $this->check($item);
                if ($ok) {
                    $scoreHit += $item['weight'];
                }
                $results[] = [
                    'group' => $group,
                    'key' => $item['key'],
                    'title' => $item['title'],
                    'ok' => $ok,
                    'weight' => $item['weight'],
                    'hint' => $item['hint'] ?? null,
                ];
            }
        }

        $percent = $scoreTotal ? round(($scoreHit / $scoreTotal) * 100, 1) : 0.0;

        // Optional Git info
        $git = $this->gitInfo();

        $report = [
            'percent' => $percent,
            'score' => ['hit' => $scoreHit, 'total' => $scoreTotal],
            'git' => $git,
            'results' => $results,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info("IMDC Progress: {$percent}%  (score {$scoreHit}/{$scoreTotal})");
            if ($git) {
                $remote = isset($git['remote']) && $git['remote'] !== null ? $git['remote'] : '-';
                $this->line("Git: branch {$git['branch']}  ahead {$git['ahead']} / behind {$git['behind']}  remote: {$remote}");
            }
            $this->newLine();
            $this->table(
                ['Group', 'Item', 'OK', 'Weight', 'Hint'],
                array_map(function ($r) {
                    return [$r['group'], $r['key'].' — '.$r['title'], $r['ok'] ? '✓' : '-', (string) $r['weight'], $r['hint'] ?? ''];
                }, $results)
            );
        }

        return 0;
    }

    private function check(array $item): bool
    {
        $type = $item['type'];
        $arg = $item['arg'];

        try {
            switch ($type) {
                case 'file':
                    return File::exists(base_path($arg));
                case 'route':
                    $routes = app('router')->getRoutes();
                    $methodNeed = strtoupper($item['method'] ?? 'GET');
                    $pathNeed = trim($arg, '/');
                    foreach ($routes as $r) {
                        $methods = array_map('strtoupper', (array) $r->methods());
                        $uri = trim($r->uri(), '/');
                        if (in_array($methodNeed, $methods, true) && $uri === $pathNeed) {
                            return true;
                        }
                    }

                    return false;
                case 'table':
                    return Schema::hasTable($arg);
                case 'column':
                    [$table,$col] = explode('.', $arg, 2);

                    return Schema::hasColumn($table, $col);
                case 'config':
                    return (bool) config($arg, false) || config($arg, null) !== null;
                case 'class':
                    return class_exists($arg);
                default:
                    return false;
            }
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function baseline(): array
    {
        // Each item has: key, title, type(file/route/table/column/config/class), arg, weight, hint?
        return [
            'Core' => [
                ['key' => 'health_ping', 'title' => '/api/health ping', 'type' => 'route', 'arg' => 'api/health', 'method' => 'GET', 'weight' => 2],
                ['key' => 'health_live', 'title' => '/api/health/live', 'type' => 'route', 'arg' => 'api/health/live', 'method' => 'GET', 'weight' => 2],
                ['key' => 'health_ready', 'title' => '/api/health/ready', 'type' => 'route', 'arg' => 'api/health/ready', 'method' => 'GET', 'weight' => 2],
                ['key' => 'openapi_json', 'title' => '/api/health/openapi', 'type' => 'route', 'arg' => 'api/health/openapi', 'method' => 'GET', 'weight' => 2],
                ['key' => 'api_response', 'title' => 'ApiResponse helper', 'type' => 'class', 'arg' => 'App\\Support\\ApiResponse', 'weight' => 2],
            ],
            'Security' => [
                ['key' => 'security_headers', 'title' => 'SecurityHeaders middleware', 'type' => 'class', 'arg' => 'App\\Http\\Middleware\\SecurityHeaders', 'weight' => 2],
                ['key' => 'csp_config', 'title' => 'config/security.php', 'type' => 'file', 'arg' => 'config/security.php', 'weight' => 2],
                ['key' => 'pii_log', 'title' => 'PiiSafeRequestLog middleware', 'type' => 'class', 'arg' => 'App\\Http\\Middleware\\PiiSafeRequestLog', 'weight' => 1],
                ['key' => 'rate_adv', 'title' => 'AdvancedRateLimiter middleware', 'type' => 'class', 'arg' => 'App\\Http\\Middleware\\AdvancedRateLimiter', 'weight' => 2],
                ['key' => 'cors_adv', 'title' => 'AdvancedCors middleware', 'type' => 'class', 'arg' => 'App\\Http\\Middleware\\AdvancedCors', 'weight' => 2],
            ],
            'RBAC' => [
                ['key' => 'rbac_route', 'title' => 'RBAC roles route', 'type' => 'route', 'arg' => 'api/rbac/roles', 'method' => 'GET', 'weight' => 2],
                ['key' => 'require_role', 'title' => 'RequireRole middleware', 'type' => 'class', 'arg' => 'App\\Http\\Middleware\\RequireRole', 'weight' => 2],
            ],
            'Social' => [
                ['key' => 'posts_table', 'title' => 'posts table', 'type' => 'table', 'arg' => 'posts', 'weight' => 2],
                ['key' => 'messages_table', 'title' => 'messages table', 'type' => 'table', 'arg' => 'messages', 'weight' => 2],
                ['key' => 'safe_rooms_table', 'title' => 'safe_rooms table', 'type' => 'table', 'arg' => 'safe_rooms', 'weight' => 1],
                ['key' => 'posts_route', 'title' => 'GET /api/social/posts', 'type' => 'route', 'arg' => 'api/social/posts', 'method' => 'GET', 'weight' => 2],
            ],
            'Marketplace' => [
                ['key' => 'shops_table', 'title' => 'shops table', 'type' => 'table', 'arg' => 'shops', 'weight' => 2],
                ['key' => 'products_table', 'title' => 'products table', 'type' => 'table', 'arg' => 'products', 'weight' => 2],
                ['key' => 'orders_table', 'title' => 'orders table', 'type' => 'table', 'arg' => 'orders', 'weight' => 2],
                ['key' => 'order_items_table', 'title' => 'order_items table', 'type' => 'table', 'arg' => 'order_items', 'weight' => 2],
                ['key' => 'inventory_table', 'title' => 'inventory table', 'type' => 'table', 'arg' => 'inventory', 'weight' => 1],
                ['key' => 'orders_status_api', 'title' => 'PATCH /api/market/orders/{id}/status', 'type' => 'route', 'arg' => 'api/market/orders/{orderId}/status', 'method' => 'PATCH', 'weight' => 2],
            ],
            'Identity' => [
                ['key' => 'wallets_table', 'title' => 'wallets table', 'type' => 'table', 'arg' => 'wallets', 'weight' => 2],
                ['key' => 'did_profiles_table', 'title' => 'did_profiles table', 'type' => 'table', 'arg' => 'did_profiles', 'weight' => 2],
                ['key' => 'nft_transfers_table', 'title' => 'nft_transfers table', 'type' => 'table', 'arg' => 'nft_transfers', 'weight' => 1],
                ['key' => 'wallets_route', 'title' => 'GET /api/identity/wallets', 'type' => 'route', 'arg' => 'api/identity/wallets', 'method' => 'GET', 'weight' => 1],
            ],
            'Infra' => [
                ['key' => 'metrics', 'title' => '/api/metrics', 'type' => 'route', 'arg' => 'api/metrics', 'method' => 'GET', 'weight' => 2],
                ['key' => 'jobs_table', 'title' => 'jobs table', 'type' => 'table', 'arg' => 'jobs', 'weight' => 1],
                ['key' => 'failed_jobs_table', 'title' => 'failed_jobs table', 'type' => 'table', 'arg' => 'failed_jobs', 'weight' => 1],
                ['key' => 'release_builder', 'title' => 'ImdcBuildRelease command', 'type' => 'class', 'arg' => 'App\\Console\\Commands\\ImdcBuildRelease', 'weight' => 1],
                ['key' => 'sanity_cmd', 'title' => 'ImdcSanity command', 'type' => 'class', 'arg' => 'App\\Console\\Commands\\ImdcSanity', 'weight' => 1],
                ['key' => 'schema_audit', 'title' => 'ImdcSchemaAudit command', 'type' => 'class', 'arg' => 'App\\Console\\Commands\\ImdcSchemaAudit', 'weight' => 1],
            ],
            'DB Hardening' => [
                ['key' => 'schema_harmonizer', 'title' => 'Schema Harmonizer migration present', 'type' => 'file', 'arg' => 'database/migrations/2025_08_12_030000_schema_harmonizer_fix_missing_columns.php', 'weight' => 2],
                ['key' => 'constraints_mig', 'title' => 'Data backfill & constraints migration', 'type' => 'file', 'arg' => 'database/migrations/2025_08_12_040000_data_backfill_and_constraints.php', 'weight' => 2],
                ['key' => 'index_cleanup', 'title' => 'Index cleanup migration', 'type' => 'file', 'arg' => 'database/migrations/2025_08_12_010000_cleanup_index_names.php', 'weight' => 1],
            ],
            'Observability' => [
                ['key' => 'sentry_cfg', 'title' => 'Sentry config', 'type' => 'file', 'arg' => 'config/sentry.php', 'weight' => 1],
                ['key' => 'sentry_mw', 'title' => 'SentryContext middleware', 'type' => 'class', 'arg' => 'App\\Http\\Middleware\\SentryContext', 'weight' => 1],
                ['key' => 'health_probe', 'title' => 'health_probe.sh', 'type' => 'file', 'arg' => 'scripts/health/health_probe.sh', 'weight' => 1],
                ['key' => 'log_json', 'title' => 'Nginx JSON log', 'type' => 'file', 'arg' => 'docker/nginx/imdc.conf', 'weight' => 1],
                ['key' => 'prom_doc', 'title' => 'MONITORING_PROMETHEUS.md', 'type' => 'file', 'arg' => 'docs/MONITORING_PROMETHEUS.md', 'weight' => 1],
            ],
            'Ops' => [
                ['key' => 'docker_compose', 'title' => 'docker/compose.dev.yml', 'type' => 'file', 'arg' => 'docker/compose.dev.yml', 'weight' => 1],
                ['key' => 'supervisor_queue', 'title' => 'supervisor queue conf', 'type' => 'file', 'arg' => 'supervisor/imdc-queue.conf', 'weight' => 1],
                ['key' => 'fail2ban_filter', 'title' => 'fail2ban filter', 'type' => 'file', 'arg' => 'security/fail2ban/filter.d/imdc-nginx-json.conf', 'weight' => 1],
                ['key' => 'release_notes', 'title' => 'RELEASE_NOTES.md', 'type' => 'file', 'arg' => 'docs/RELEASE_NOTES.md', 'weight' => 1],
            ],
            'Config' => [
                ['key' => 'cors_cfg', 'title' => 'config/cors.php', 'type' => 'file', 'arg' => 'config/cors.php', 'weight' => 1],
                ['key' => 'rate_cfg', 'title' => 'config/rate.php', 'type' => 'file', 'arg' => 'config/rate.php', 'weight' => 1],
                ['key' => 'feature_flags', 'title' => 'feature_flags table', 'type' => 'table', 'arg' => 'feature_flags', 'weight' => 1],
            ],
        ];
    }

    private function gitInfo(): ?array
    {
        try {
            $root = base_path();
            $inside = $this->proc('git rev-parse --is-inside-work-tree', $root);
            if (trim($inside) !== 'true') {
                return null;
            }

            $branch = trim($this->proc('git rev-parse --abbrev-ref HEAD', $root));
            $remote = trim($this->proc('git remote get-url origin', $root));
            $remote = $remote !== '' ? $remote : null;

            $ahead = 0;
            $behind = 0;
            try {
                $upstream = trim($this->proc('git rev-parse --abbrev-ref --symbolic-full-name @{u}', $root));
                if ($upstream) {
                    $counts = trim($this->proc('git rev-list --left-right --count HEAD...@{u}', $root));
                    if ($counts && strpos($counts, "\t") !== false) {
                        [$behind, $ahead] = array_map('intval', explode("\t", $counts));
                    }
                }
            } catch (\Throwable $e) {
            }

            return ['branch' => $branch ?: '-', 'remote' => $remote, 'ahead' => $ahead, 'behind' => $behind];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function proc(string $cmd, string $cwd): string
    {
        $p = Process::fromShellCommandline($cmd, $cwd, null, null, 5);
        $p->run();

        return $p->isSuccessful() ? (string) $p->getOutput() : '';
    }
}
