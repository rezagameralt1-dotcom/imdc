<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('orders')->statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');

        // Idempotency: Only create if not exists
        if (!Schema::connection('orders')->hasTable('orders')) {
            Schema::connection('orders')->create('orders', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                $table->string('shop_customer_id');
                $table->string('status')->default('pending');
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->string('currency', 3)->default('USD');
                $table->json('meta')->nullable();
                $table->string('trace_id')->nullable()->index();
                $table->timestamps();
                $table->index('shop_customer_id');
            });
        }

        // Idempotency: Only create if not exists
        if (!Schema::connection('orders')->hasTable('order_items')) {
            Schema::connection('orders')->create('order_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('order_id');
                $table->uuid('product_id');
                $table->string('product_name');
                $table->decimal('unit_price', 12, 2);
                $table->unsignedInteger('quantity');
                $table->decimal('line_total', 12, 2);
                $table->timestamps();

                $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('orders')->dropIfExists('order_items');
        Schema::connection('orders')->dropIfExists('orders');
    }
};

