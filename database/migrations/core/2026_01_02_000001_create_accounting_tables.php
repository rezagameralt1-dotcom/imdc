<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('core')->statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');

        // Accounting Ledger (General Ledger)
        if (!Schema::connection('core')->hasTable('accounting_ledger')) {
            Schema::connection('core')->create('accounting_ledger', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                $table->string('account_code', 50)->index();
                $table->string('account_name');
                $table->decimal('debit', 15, 2)->default(0);
                $table->decimal('credit', 15, 2)->default(0);
                $table->string('currency', 3)->default('USD');
                $table->string('reference_type')->nullable(); // 'order', 'voucher', etc.
                $table->uuid('reference_id')->nullable();
                $table->text('description')->nullable();
                $table->string('trace_id')->nullable()->index();
                $table->timestamp('posted_at')->useCurrent();
                $table->timestamps();
                
                $table->index(['reference_type', 'reference_id']);
                $table->index('posted_at');
            });
        }

        // Accounting Vouchers (Journal Entries)
        if (!Schema::connection('core')->hasTable('accounting_vouchers')) {
            Schema::connection('core')->create('accounting_vouchers', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                $table->string('voucher_number')->unique();
                $table->string('voucher_type', 50); // 'payment', 'refund', 'adjustment'
                $table->uuid('reference_id')->nullable(); // order_id, etc.
                $table->string('reference_type')->nullable();
                $table->decimal('total_amount', 15, 2);
                $table->string('currency', 3)->default('USD');
                $table->string('status')->default('draft'); // draft, posted, cancelled
                $table->text('description')->nullable();
                $table->string('trace_id')->nullable()->index();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();
                
                $table->index(['reference_type', 'reference_id']);
                $table->index('status');
            });
        }

        // Voucher Entries (Lines in a voucher)
        // Must be created after accounting_vouchers exists (FK dependency)
        if (!Schema::connection('core')->hasTable('accounting_voucher_entries')) {
            Schema::connection('core')->create('accounting_voucher_entries', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                $table->unsignedBigInteger('voucher_id'); // FK to accounting_vouchers.id (bigint)
                $table->string('account_code', 50);
                $table->string('account_name');
                $table->decimal('debit', 15, 2)->default(0);
                $table->decimal('credit', 15, 2)->default(0);
                $table->text('description')->nullable();
                $table->integer('sequence')->default(0);
                $table->timestamps();
                
                // Foreign key: voucher_id (bigint) -> accounting_vouchers.id (bigint)
                $table->foreign('voucher_id')->references('id')->on('accounting_vouchers')->onDelete('cascade');
                $table->index('voucher_id');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('accounting_voucher_entries');
        Schema::connection('core')->dropIfExists('accounting_vouchers');
        Schema::connection('core')->dropIfExists('accounting_ledger');
    }
};
