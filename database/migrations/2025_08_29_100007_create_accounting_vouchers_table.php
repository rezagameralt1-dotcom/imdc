<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('accounting_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->string('type', 16);   // INVOICE, RECEIPT, REFUND
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status', 16)->default('pending'); // pending, synced, failed
            $table->json('payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
        Schema::table('accounting_vouchers', function (Blueprint $table) {
            $table->index(['order_id', 'type', 'status']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('accounting_vouchers');
    }
};
