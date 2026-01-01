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

        Schema::connection('core')->create('pub.did_order_links', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('did_id')->index();
            $table->uuid('order_id')->index();
            $table->string('order_db_connection')->default('orders');
            $table->string('scope')->default('ownership');
            $table->uuid('created_by')->nullable()->index();
            $table->timestamps();

            // Unique constraint
            $table->unique(['did_id', 'order_id', 'scope']);

            // Foreign key to did_profiles if schema supports it
            // Note: order_id has no FK (cross-DB reference)
            try {
                $table->foreign('did_id')->references('id')->on('did_profiles')->onDelete('cascade');
            } catch (\Exception $e) {
                // FK may not be supported in this schema version, skip
            }
        });
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('pub.did_order_links');
    }
};
