<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::connection('inventory')->create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id'); // No FK - products table is in products DB
            $table->string('type', 16); // IN, OUT, RESERVE, RELEASE, ADJUST
            $table->integer('quantity');
            $table->string('reason')->nullable();
            $table->string('ref_type')->nullable(); // e.g., 'order'
            $table->string('ref_id')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable(); // No FK - users table is in core DB
            $table->timestamps();
        });
        Schema::connection('inventory')->table('stock_movements', function (Blueprint $table) {
            $table->index(['product_id', 'type']);
        });
    }
    public function down(): void {
        Schema::connection('inventory')->dropIfExists('stock_movements');
    }
};
