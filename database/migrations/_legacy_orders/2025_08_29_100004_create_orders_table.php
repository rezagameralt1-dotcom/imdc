<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::connection('orders')->create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // No FK - users table is in core DB
            $table->string('status', 20)->default('pending'); // pending, paid, fulfilled, canceled, refunded
            $table->char('currency', 3)->default('IRR');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('shipping_total', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
        Schema::connection('orders')->table('orders', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
        });
    }
    public function down(): void {
        Schema::connection('orders')->dropIfExists('orders');
    }
};
