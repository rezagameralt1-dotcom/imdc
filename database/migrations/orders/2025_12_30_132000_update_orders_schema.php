<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getName() !== 'orders') {
            return;
        }

        // Enable pgcrypto for UUID generation if not already enabled
        DB::connection('orders')->statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');

        Schema::connection('orders')->table('orders', function (Blueprint $table) {
            if (Schema::connection('orders')->hasColumn('orders', 'user_id')) {
                $table->dropColumn('user_id');
            }
            if (! Schema::connection('orders')->hasColumn('orders', 'shop_customer_id')) {
                $table->string('shop_customer_id', 36)->index();
            }
            if (Schema::connection('orders')->hasColumn('orders', 'inventory_status')) {
                $table->dropColumn('inventory_status');
            }
            $table->string('status', 32)->default('pending')->change();
            $table->decimal('total_amount', 12, 2)->default(0)->change();
            $table->string('currency', 3)->default('USD')->change();
        });

        // Recreate order_items to enforce correct types (will drop existing data)
        Schema::connection('orders')->dropIfExists('order_items');
        Schema::connection('orders')->create('order_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('order_id');
            $table->uuid('product_id');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getName() !== 'orders') {
            return;
        }

        Schema::connection('orders')->table('orders', function (Blueprint $table) {
            if (! Schema::connection('orders')->hasColumn('orders', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable();
            }
            if (Schema::connection('orders')->hasColumn('orders', 'shop_customer_id')) {
                $table->dropColumn('shop_customer_id');
            }
            if (! Schema::connection('orders')->hasColumn('orders', 'inventory_status')) {
                $table->string('inventory_status')->default('pending');
            }
        });

        Schema::connection('orders')->table('order_items', function (Blueprint $table) {
            if (Schema::connection('orders')->hasColumn('order_items', 'line_total')) {
                $table->dropColumn('line_total');
            }
            if (! Schema::connection('orders')->hasColumn('order_items', 'subtotal')) {
                $table->decimal('subtotal', 12, 2)->default(0);
            }
        });
    }
};

