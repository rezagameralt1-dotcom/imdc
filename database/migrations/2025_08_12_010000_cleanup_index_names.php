<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalize and ensure performance indexes exist, with safe naming (<=63 chars).
 * - Does NOT drop any existing indexes (avoids destructive ops).
 * - Creates missing indexes with canonical short names: {table}_{cols_joined}_idx
 * - Works for single- and multi-column indexes.
 */
return new class extends Migration
{
    private function sanitize(string $name): string
    {
        $name = strtolower(preg_replace('/[^a-z0-9_]+/', '', $name));
        if (strlen($name) <= 63) {
            return $name;
        }
        // shorten: keep beginning + hash tail to ensure uniqueness
        $hash = substr(sha1($name), 0, 8);

        return substr($name, 0, 54).'_'.$hash; // total 63
    }

    /** @param string[] $columns */
    private function ensureIndex(string $table, array $columns, ?string $name = null): void
    {
        if (! Schema::hasTable($table)) {
            throw new RuntimeException("Table '{$table}' does not exist.");
        }
        foreach ($columns as $col) {
            if (! Schema::hasColumn($table, $col)) {
                throw new RuntimeException("Column '{$table}.{$col}' does not exist.");
            }
        }

        $canonical = $this->sanitize($name ?: ($table.'_'.implode('_', $columns).'_idx'));

        // Check if any index already covers exactly these columns (order-insensitive check via pg_get_indexdef)
        $colsList = implode('","', $columns);
        $sql = <<<'SQL'
            SELECT i.relname AS index_name, pg_get_indexdef(ix.indexrelid) AS def
            FROM pg_index ix
            JOIN pg_class c ON c.oid = ix.indrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            JOIN pg_class i ON i.oid = ix.indexrelid
            WHERE n.nspname = ANY (current_schemas(true))
              AND c.relname = ?
        SQL;
        $existing = DB::select($sql, [$table]);

        // If an index exists with same column set (ignoring sort order), skip creation.
        $covered = false;
        foreach ($existing as $row) {
            $def = strtolower($row->def);
            $ok = true;
            foreach ($columns as $col) {
                if (strpos($def, '("'.strtolower($col).'"') === false && strpos($def, '('.strtolower($col).')') === false) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $covered = true;
                break;
            }
        }

        if (! $covered) {
            $cols = '"'.$colsList.'"';
            $sqlCreate = sprintf('CREATE INDEX IF NOT EXISTS %s ON "%s" (%s)', $canonical, $table, $cols);
            DB::statement($sqlCreate);
        }
    }

    public function up(): void
    {
        // Social
        $this->ensureIndex('posts', ['user_id']);
        $this->ensureIndex('posts', ['status']);
        $this->ensureIndex('posts', ['created_at']);
        $this->ensureIndex('messages', ['sender_id']);
        $this->ensureIndex('messages', ['receiver_id']);
        $this->ensureIndex('messages', ['safe_room_id']);
        $this->ensureIndex('messages', ['created_at']);
        $this->ensureIndex('safe_rooms', ['name']);

        // Marketplace
        $this->ensureIndex('shops', ['owner_id']);
        $this->ensureIndex('products', ['shop_id']);
        $this->ensureIndex('orders', ['user_id']);
        $this->ensureIndex('orders', ['status']);
        $this->ensureIndex('orders', ['created_at']);
        $this->ensureIndex('order_items', ['order_id']);
        $this->ensureIndex('order_items', ['product_id']);
        $this->ensureIndex('inventory', ['product_id']);
        // composite commonly used
        $this->ensureIndex('order_items', ['order_id', 'product_id']);

        // Wallets / NFTs
        $this->ensureIndex('wallets', ['user_id']);
        $this->ensureIndex('nft_transfers', ['from_wallet_id']);
        $this->ensureIndex('nft_transfers', ['to_wallet_id']);
        $this->ensureIndex('nft_transfers', ['transferred_at']);

        // Identity
        $this->ensureIndex('did_profiles', ['user_id']);

        // DAO
        $this->ensureIndex('proposals', ['creator_id']);
        $this->ensureIndex('proposals', ['starts_at']);
        $this->ensureIndex('proposals', ['ends_at']);
        $this->ensureIndex('votes', ['proposal_id']);
        $this->ensureIndex('votes', ['user_id']);
        $this->ensureIndex('votes', ['proposal_id', 'user_id']); // supports unique check scans

        // Accounting / Audit
        $this->ensureIndex('accounts', ['user_id']);
        $this->ensureIndex('audit_logs', ['user_id']);
        $this->ensureIndex('audit_logs', ['event']);
        $this->ensureIndex('audit_logs', ['created_at']);

        // Courses
        $this->ensureIndex('courses', ['teacher_id']);
        $this->ensureIndex('classes', ['course_id']);
        $this->ensureIndex('enrollments', ['class_id']);
        $this->ensureIndex('enrollments', ['user_id']);
        $this->ensureIndex('skill_nfts', ['user_id']);
        $this->ensureIndex('skill_nfts', ['course_id']);

        // Reports / Legal
        $this->ensureIndex('reports', ['name']);
        $this->ensureIndex('legal_cases', ['owner_id']);
        $this->ensureIndex('legal_cases', ['status']);
    }

    public function down(): void
    {
        // No destructive rollback here.
    }
};
