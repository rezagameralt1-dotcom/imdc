<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function ensureTable(string $table): void
    {
        if (!Schema::hasTable($table)) {
            throw new RuntimeException("Table '{$table}' does not exist. Run previous bootstrap migrations first.");
        }
    }

    private function ensureColumn(string $table, string $column): void
    {
        if (!Schema::hasColumn($table, $column)) {
            throw new RuntimeException("Column '{$table}.{$column}' does not exist. Schema is out of sync.");
        }
    }

    private function constraintExists(string $table, string $name): bool
    {
        $row = DB::selectOne(
            "SELECT 1 FROM pg_constraint WHERE conname = ? LIMIT 1",
            [$name]
        );
        return (bool) $row;
    }

    private function addCheck(string $table, string $name, string $expression): void
    {
        $this->ensureTable($table);
        if ($this->constraintExists($table, $name)) {
            return;
        }
        DB::statement("ALTER TABLE \"{$table}\" ADD CONSTRAINT {$name} CHECK ({$expression})");
    }

    private function setNotNull(string $table, string $column): void
    {
        $this->ensureTable($table);
        $this->ensureColumn($table, $column);
        DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column}\" SET NOT NULL");
    }

    private function addUnique(string $table, string $name, array $columns): void
    {
        $this->ensureTable($table);
        if ($this->constraintExists($table, $name)) {
            return;
        }
        $cols = implode('","', $columns);
        DB::statement("ALTER TABLE \"{$table}\" ADD CONSTRAINT {$name} UNIQUE (\"{$cols}\")");
    }

    public function up(): void
    {
        // --- posts ---
        $this->setNotNull('posts','user_id');
        $this->addCheck('posts','posts_status_chk', "\"status\" IN ('draft','published','archived')");

        // --- messages ---
        $this->setNotNull('messages','sender_id');

        // --- shops/products ---
        $this->setNotNull('shops','owner_id');
        $this->setNotNull('products','shop_id');
        // sku already unique via migration; enforce name+shop uniqueness to avoid duplicates
        $this->addUnique('products','products_shop_name_uniq', ['shop_id','name']);

        // --- orders/order_items ---
        $this->setNotNull('orders','user_id');
        $this->addCheck('orders','orders_status_chk', "\"status\" IN ('pending','paid','shipped','cancelled','refunded')");
        $this->setNotNull('order_items','order_id');
        $this->setNotNull('order_items','product_id');
        $this->addCheck('order_items','order_items_qty_chk', "\"qty\" > 0");
        $this->addCheck('order_items','order_items_price_chk', "\"price\" >= 0");

        // --- inventory ---
        $this->setNotNull('inventory','product_id');
        $this->addCheck('inventory','inventory_stock_chk', "\"stock\" >= 0");

        // --- wallets ---
        $this->setNotNull('wallets','user_id');

        // --- nft_transfers ---
        $this->addCheck('nft_transfers','nft_transfers_wallet_chk', "(\"from_wallet_id\" IS NOT NULL OR \"to_wallet_id\" IS NOT NULL)");

        // --- did_profiles ---
        $this->setNotNull('did_profiles','user_id');

        // --- dao ---
        $this->setNotNull('proposals','creator_id');
        $this->setNotNull('votes','proposal_id');
        $this->setNotNull('votes','user_id');
        $this->addUnique('votes','votes_unique_user_per_proposal', ['proposal_id','user_id']);

        // --- accounting/audit ---
        $this->addCheck('accounts','accounts_code_len_chk', "char_length(\"code\") >= 3");
        $this->addCheck('audit_logs','audit_logs_event_len_chk', "char_length(\"event\") >= 3");

        // --- courses/classes/enrollments/skill_nfts ---
        $this->setNotNull('classes','course_id');
        $this->setNotNull('enrollments','class_id');
        $this->setNotNull('enrollments','user_id');
        $this->addUnique('enrollments','enrollments_unique_user_per_class', ['class_id','user_id']);

        // --- reports/legal ---
        $this->addCheck('reports','reports_name_len_chk', "char_length(\"name\") >= 3");
        $this->addCheck('legal_cases','legal_cases_status_chk', "\"status\" IN ('open','closed','appeal')");
    }

    public function down(): void
    {
        // Safe no-op; removing constraints en masse can be dangerous.
    }
};
