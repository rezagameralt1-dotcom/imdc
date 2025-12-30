<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('inventory')->create('inventory_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('product_id')->unique();
            $table->integer('available_quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->timestamps();
        });

        Schema::connection('inventory')->create('inventory_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->uuid('order_id');
            $table->unsignedInteger('quantity');
            $table->string('status')->default('pending');
            $table->string('reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'order_id']);
        });

        Schema::connection('inventory')->create('inventory_movements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('product_id');
            $table->integer('delta_available');
            $table->integer('delta_reserved');
            $table->string('reason')->nullable();
            $table->string('trace_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection('inventory')->dropIfExists('inventory_movements');
        Schema::connection('inventory')->dropIfExists('inventory_reservations');
        Schema::connection('inventory')->dropIfExists('inventory_items');
    }
};

