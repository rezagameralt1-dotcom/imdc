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

        // Check if table already exists (idempotent)
        if (Schema::connection('core')->hasTable('pub.place_links')) {
            return;
        }

        Schema::connection('core')->create('pub.place_links', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('place_id')->comment('References pub.places.id');
            $table->uuid('nft_id')->nullable()->comment('References nfts table (if linking NFT)');
            $table->uuid('did_id')->nullable()->comment('References did_profiles.id (if linking DID)');
            $table->string('link_type')->default('nft')->comment('nft, did, or other');
            $table->jsonb('metadata')->nullable();
            $table->uuid('created_by')->nullable()->comment('User ID from core DB (if available)');
            $table->timestamps();

            // Unique constraint: one link per place-nft or place-did pair
            $table->unique(['place_id', 'nft_id', 'did_id']);

            $table->index(['place_id']);
            $table->index(['nft_id']);
            $table->index(['did_id']);
            $table->index(['link_type']);

            // Foreign keys
            try {
                $table->foreign('place_id')->references('id')->on('pub.places')->onDelete('cascade');
                // Note: nft_id and did_id foreign keys may reference tables in different schemas
                // We'll skip FK constraints for cross-schema references
            } catch (\Exception $e) {
                // FK may not be supported in this schema version, skip
            }
        });
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('pub.place_links');
    }
};
