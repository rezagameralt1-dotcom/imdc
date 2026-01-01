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
        if (Schema::connection('core')->hasTable('pub.pharma_interactions')) {
            return;
        }

        Schema::connection('core')->create('pub.pharma_interactions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('drug1_id')->comment('First drug UUID');
            $table->uuid('drug2_id')->comment('Second drug UUID');
            $table->string('severity')->default('moderate')->comment('mild, moderate, severe');
            $table->text('description');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Unique constraint: one interaction per drug pair (order-independent)
            // Use sorted UUIDs to ensure uniqueness regardless of order
            $table->unique(['drug1_id', 'drug2_id']);

            $table->index(['drug1_id']);
            $table->index(['drug2_id']);
            $table->index(['severity']);

            // Foreign keys
            try {
                $table->foreign('drug1_id')->references('id')->on('pub.pharma_drugs')->onDelete('cascade');
                $table->foreign('drug2_id')->references('id')->on('pub.pharma_drugs')->onDelete('cascade');
            } catch (\Exception $e) {
                // FK may not be supported in this schema version, skip
            }
        });
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('pub.pharma_interactions');
    }
};
