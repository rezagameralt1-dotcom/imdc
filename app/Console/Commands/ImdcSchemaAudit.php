<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImdcSchemaAudit extends Command
{
    protected $signature = 'imdc:schema-audit {--json : Output JSON}';

    protected $description = 'Audit expected tables/columns/index coverage and report gaps';

    public function handle(): int
    {
        $expect = [
            'posts' => ['user_id', 'status', 'created_at'],
            'messages' => ['sender_id', 'receiver_id', 'safe_room_id', 'created_at'],
            'safe_rooms' => ['name'],
            'shops' => ['owner_id'],
            'products' => ['shop_id'],
            'orders' => ['user_id', 'status', 'created_at'],
            'order_items' => ['order_id', 'product_id'],
            'inventory' => ['product_id'],
            'wallets' => ['user_id', 'address'],
            'nft_transfers' => ['from_wallet_id', 'to_wallet_id', 'transferred_at'],
            'did_profiles' => ['user_id', 'did'],
            'proposals' => ['creator_id', 'starts_at', 'ends_at'],
            'votes' => ['proposal_id', 'user_id'],
            'accounts' => ['user_id', 'code'],
            'audit_logs' => ['user_id', 'event', 'created_at'],
            'courses' => ['teacher_id'],
            'classes' => ['course_id', 'code'],
            'enrollments' => ['class_id', 'user_id'],
            'skill_nfts' => ['user_id', 'course_id'],
            'reports' => ['name'],
            'legal_cases' => ['owner_id', 'status'],
        ];

        $report = ['missing_tables' => [], 'missing_columns' => [], 'missing_indexes' => []];

        foreach ($expect as $table => $cols) {
            if (! Schema::hasTable($table)) {
                $report['missing_tables'][] = $table;

                continue;
            }
            foreach ($cols as $col) {
                if (! Schema::hasColumn($table, $col)) {
                    $report['missing_columns'][] = "{$table}.{$col}";
                } else {
                    // check index coverage
                    $hit = DB::selectOne(
                        'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexdef ILIKE ? LIMIT 1',
                        [$table, "%(\"{$col}\")%"]
                    );
                    if (! $hit) {
                        $report['missing_indexes'][] = "{$table}.{$col}";
                    }
                }
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info('Schema audit report');
            $this->line('- Missing tables: '.(count($report['missing_tables']) ?: 0));
            foreach ($report['missing_tables'] as $t) {
                $this->line("  • {$t}");
            }
            $this->line('- Missing columns: '.(count($report['missing_columns']) ?: 0));
            foreach ($report['missing_columns'] as $c) {
                $this->line("  • {$c}");
            }
            $this->line('- Columns without index coverage: '.(count($report['missing_indexes']) ?: 0));
            foreach ($report['missing_indexes'] as $i) {
                $this->line("  • {$i}");
            }
        }

        return 0;
    }
}
