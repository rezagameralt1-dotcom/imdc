<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->integer('stock_on_hand')->default(0);
            $table->integer('stock_reserved')->default(0);
            $table->integer('reorder_level')->default(0);
            $table->timestamps();
        });

        // ستون محاسباتی stock_available (Postgres: generated column)
        DB::statement("ALTER TABLE inventories
            ADD COLUMN stock_available int GENERATED ALWAYS AS (GREATEST(stock_on_hand - stock_reserved, 0)) STORED");
    }
    public function down(): void {
        Schema::dropIfExists('inventories');
    }
};
