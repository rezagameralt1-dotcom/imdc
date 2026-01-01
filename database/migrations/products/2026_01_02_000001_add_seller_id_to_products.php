<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('products')->table('products', function (Blueprint $table) {
            if (!Schema::connection('products')->hasColumn('products', 'seller_id')) {
                $table->uuid('seller_id')->nullable()->after('id');
                $table->index('seller_id');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('products')->table('products', function (Blueprint $table) {
            if (Schema::connection('products')->hasColumn('products', 'seller_id')) {
                $table->dropIndex(['seller_id']);
                $table->dropColumn('seller_id');
            }
        });
    }
};
