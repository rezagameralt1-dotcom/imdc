<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create pub schema if it doesn't exist
        DB::connection('core')->statement('CREATE SCHEMA IF NOT EXISTS pub');

        Schema::connection('core')->create('pub.order_nft_links', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('order_id')->index();
            $table->uuid('nft_id')->index();
            $table->string('orders_db_connection')->default('orders');
            $table->string('nfts_db_connection')->default('nfts');
            $table->string('purpose')->default('fulfillment');
            $table->uuid('created_by')->nullable()->index();
            $table->timestamps();

            // Unique constraint
            $table->unique(['order_id', 'nft_id', 'purpose']);

            // Note: No FKs (cross-DB references)
        });
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('pub.order_nft_links');
    }
};
