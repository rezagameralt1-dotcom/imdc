<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('orders')->table('orders', function (Blueprint $table) {
            if (!Schema::connection('orders')->hasColumn('orders', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique()->after('trace_id');
                $table->index('idempotency_key');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('orders')->table('orders', function (Blueprint $table) {
            if (Schema::connection('orders')->hasColumn('orders', 'idempotency_key')) {
                $table->dropIndex(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            }
        });
    }
};
