<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema Harmonizer (Non-destructive)
 *
 * این مایگریشن هر ستون کلیدی که در کد استفاده می‌شود را اگر وجود نداشته باشد
 * با نوع دادهٔ مناسب اضافه می‌کند تا مایگریشن‌های ایندکس/کانسترینت بعدی خطا ندهند.
 * - هیچ ستونی حذف یا تغییر نوع داده نمی‌شود.
 * - ستون‌ها به‌صورت NULLable اضافه می‌شوند؛ مایگریشن سخت‌گیرانه (m12) بعداً NOT NULL را enforce می‌کند.
 */
return new class extends Migration
{
    private function ensureTable(string $table): bool
    {
        return Schema::hasTable($table);
    }

    private function ensureInteger(string $table, string $column, bool $big = false): void
    {
        if (! $this->ensureTable($table) || Schema::hasColumn($table, $column)) {
            return;
        }
        $type = $big ? 'BIGINT' : 'INTEGER';
        DB::statement("ALTER TABLE \"{$table}\" ADD COLUMN \"{$column}\" {$type} NULL");
    }

    private function ensureVarchar(string $table, string $column, int $len = 255): void
    {
        if (! $this->ensureTable($table) || Schema::hasColumn($table, $column)) {
            return;
        }
        DB::statement("ALTER TABLE \"{$table}\" ADD COLUMN \"{$column}\" VARCHAR({$len}) NULL");
    }

    private function ensureJson(string $table, string $column): void
    {
        if (! $this->ensureTable($table) || Schema::hasColumn($table, $column)) {
            return;
        }
        DB::statement("ALTER TABLE \"{$table}\" ADD COLUMN \"{$column}\" JSON NULL");
    }

    private function ensureTimestamp(string $table, string $column): void
    {
        if (! $this->ensureTable($table) || Schema::hasColumn($table, $column)) {
            return;
        }
        DB::statement("ALTER TABLE \"{$table}\" ADD COLUMN \"{$column}\" TIMESTAMP NULL");
    }

    public function up(): void
    {
        // --- Social
        $this->ensureInteger('posts', 'user_id');
        $this->ensureVarchar('posts', 'status', 32);

        $this->ensureInteger('messages', 'sender_id');
        $this->ensureInteger('messages', 'receiver_id');
        $this->ensureInteger('messages', 'safe_room_id');
        $this->ensureVarchar('messages', 'body', 10000);

        // --- Marketplace
        $this->ensureInteger('shops', 'owner_id');
        $this->ensureInteger('products', 'shop_id');
        $this->ensureVarchar('products', 'name', 255);
        $this->ensureVarchar('products', 'sku', 64);
        $this->ensureInteger('products', 'price');
        $this->ensureJson('products', 'meta');

        $this->ensureInteger('orders', 'user_id');
        $this->ensureVarchar('orders', 'status', 32);
        $this->ensureInteger('orders', 'total');

        $this->ensureInteger('order_items', 'order_id');
        $this->ensureInteger('order_items', 'product_id');
        $this->ensureInteger('order_items', 'qty');
        $this->ensureInteger('order_items', 'price');

        $this->ensureInteger('inventory', 'product_id');
        $this->ensureInteger('inventory', 'stock');

        // --- Identity / Wallet / NFTs
        $this->ensureInteger('wallets', 'user_id');
        $this->ensureVarchar('wallets', 'address', 255);
        $this->ensureVarchar('wallets', 'provider', 120);

        $this->ensureInteger('nft_transfers', 'from_wallet_id');
        $this->ensureInteger('nft_transfers', 'to_wallet_id');
        $this->ensureVarchar('nft_transfers', 'token_id', 255);
        $this->ensureVarchar('nft_transfers', 'contract', 255);
        $this->ensureTimestamp('nft_transfers', 'transferred_at');

        $this->ensureInteger('did_profiles', 'user_id');
        $this->ensureVarchar('did_profiles', 'did', 255);
        $this->ensureJson('did_profiles', 'credentials');

        // --- DAO
        $this->ensureInteger('proposals', 'creator_id');
        $this->ensureVarchar('proposals', 'title', 255);
        $this->ensureVarchar('proposals', 'body', 10000);
        $this->ensureTimestamp('proposals', 'starts_at');
        $this->ensureTimestamp('proposals', 'ends_at');

        $this->ensureInteger('votes', 'proposal_id');
        $this->ensureInteger('votes', 'user_id');
        $this->ensureVarchar('votes', 'value', 10);

        // --- Accounting / Audit
        $this->ensureInteger('accounts', 'user_id');
        $this->ensureVarchar('accounts', 'code', 64);
        $this->ensureVarchar('accounts', 'title', 255);

        $this->ensureInteger('audit_logs', 'user_id');
        $this->ensureVarchar('audit_logs', 'event', 255);
        $this->ensureJson('audit_logs', 'payload');
        $this->ensureTimestamp('audit_logs', 'created_at');

        // --- Courses
        $this->ensureInteger('courses', 'teacher_id');
        $this->ensureVarchar('classes', 'code', 64);
        $this->ensureInteger('classes', 'course_id');
        $this->ensureInteger('enrollments', 'class_id');
        $this->ensureInteger('enrollments', 'user_id');

        $this->ensureInteger('skill_nfts', 'user_id');
        $this->ensureInteger('skill_nfts', 'course_id');

        // --- Reports / Legal
        $this->ensureVarchar('reports', 'name', 255);
        $this->ensureJson('reports', 'definition');

        $this->ensureInteger('legal_cases', 'owner_id');
        $this->ensureVarchar('legal_cases', 'status', 32);
    }

    public function down(): void
    {
        // non-destructive: no down
    }
};
