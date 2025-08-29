<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('type', 16); // IN, OUT, RESERVE, RELEASE, ADJUST
            $table->integer('quantity');
            $table->string('reason')->nullable();
            $table->string('ref_type')->nullable(); // e.g., 'order'
            $table->string('ref_id')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['product_id', 'type']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('stock_movements');
    }
};
