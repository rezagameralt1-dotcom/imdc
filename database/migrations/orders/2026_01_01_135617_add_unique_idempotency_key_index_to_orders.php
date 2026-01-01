<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('orders')->table('orders', function (Blueprint $table) {
            // Add idempotency_key column (nullable, max 128 chars)
            if (!Schema::connection('orders')->hasColumn('orders', 'idempotency_key')) {
                $table->string('idempotency_key', 128)->nullable()->after('trace_id');
            }
        });

        // Create partial unique index: (shop_customer_id, idempotency_key) WHERE idempotency_key IS NOT NULL
        // This ensures idempotency per customer while allowing NULL values
        DB::connection('orders')->statement('
            CREATE UNIQUE INDEX IF NOT EXISTS orders_shop_customer_id_idempotency_key_unique 
            ON orders (shop_customer_id, idempotency_key) 
            WHERE idempotency_key IS NOT NULL
        ');
    }

    public function down(): void
    {
        // Drop the unique index
        DB::connection('orders')->statement('
            DROP INDEX IF EXISTS orders_shop_customer_id_idempotency_key_unique
        ');

        Schema::connection('orders')->table('orders', function (Blueprint $table) {
            if (Schema::connection('orders')->hasColumn('orders', 'idempotency_key')) {
                $table->dropColumn('idempotency_key');
            }
        });
    }
};
