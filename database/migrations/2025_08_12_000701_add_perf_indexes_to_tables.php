<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function sanitize(string $name): string
    {
        $name = strtolower(preg_replace('/[^a-z0-9_]+/', '', $name));
        if (strlen($name) > 63) {
            $name = substr($name, 0, 63);
        }
        return $name;
    }

    private function createIndex(string $table, string $column, ?string $name = null): void
    {
        if (!Schema::hasTable($table)) {
            throw new RuntimeException("Table '{$table}' does not exist for indexing.");
        }
        if (!Schema::hasColumn($table, $column)) {
            throw new RuntimeException("Column '{$table}.{$column}' does not exist for indexing.");
        }

        $index = $this->sanitize($name ?: "{$table}_{$column}_idx");
        // Use double quotes for identifiers in Postgres
        $sql = sprintf('CREATE INDEX IF NOT EXISTS %s ON "%s" ("%s")', $index, $table, $column);
        DB::statement($sql);
    }

    public function up(): void
    {
        // ---- Social / Messaging ----
        $this->createIndex('posts', 'user_id');
        $this->createIndex('posts', 'status');
        $this->createIndex('posts', 'created_at');

        $this->createIndex('messages', 'sender_id');
        $this->createIndex('messages', 'receiver_id');
        $this->createIndex('messages', 'safe_room_id');
        $this->createIndex('messages', 'created_at');

        $this->createIndex('safe_rooms', 'name');

        // ---- Marketplace / Orders / Inventory ----
        $this->createIndex('shops', 'owner_id');
        $this->createIndex('products', 'shop_id');
        $this->createIndex('orders', 'user_id');
        $this->createIndex('orders', 'status');
        $this->createIndex('orders', 'created_at');
        $this->createIndex('order_items', 'order_id');
        $this->createIndex('order_items', 'product_id');
        $this->createIndex('inventory', 'product_id');

        // ---- Wallets / NFTs ----
        $this->createIndex('wallets', 'user_id');
        $this->createIndex('nft_transfers', 'from_wallet_id');
        $this->createIndex('nft_transfers', 'to_wallet_id');
        $this->createIndex('nft_transfers', 'transferred_at');

        // ---- DID ----
        $this->createIndex('did_profiles', 'user_id');

        // ---- DAO ----
        $this->createIndex('proposals', 'creator_id');
        $this->createIndex('proposals', 'starts_at');
        $this->createIndex('proposals', 'ends_at');
        $this->createIndex('votes', 'proposal_id');
        $this->createIndex('votes', 'user_id');

        // ---- Accounting / Audit ----
        $this->createIndex('accounts', 'user_id');
        $this->createIndex('audit_logs', 'user_id');
        $this->createIndex('audit_logs', 'event');
        $this->createIndex('audit_logs', 'created_at');

        // ---- Courses / Classes ----
        $this->createIndex('courses', 'teacher_id');
        $this->createIndex('classes', 'course_id');
        $this->createIndex('enrollments', 'class_id');
        $this->createIndex('enrollments', 'user_id');
        $this->createIndex('skill_nfts', 'user_id');
        $this->createIndex('skill_nfts', 'course_id');

        // ---- Reports / Legal ----
        $this->createIndex('reports', 'name');
        $this->createIndex('legal_cases', 'owner_id');
        $this->createIndex('legal_cases', 'status');
    }

    public function down(): void
    {
        // No-op: Dropping many indexes blindly can be destructive.
        // If needed, add explicit DROP INDEX IF EXISTS statements here.
    }
};
