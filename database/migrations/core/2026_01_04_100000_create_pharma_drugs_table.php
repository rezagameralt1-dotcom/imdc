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
        if (Schema::connection('core')->hasTable('pub.pharma_drugs')) {
            return;
        }

        Schema::connection('core')->create('pub.pharma_drugs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name')->unique()->comment('Drug name');
            $table->string('generic_name')->nullable()->comment('Generic name');
            $table->text('description')->nullable();
            $table->jsonb('warnings')->nullable()->comment('Array of warning messages');
            $table->jsonb('contraindications')->nullable()->comment('Array of contraindications');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Indexes (created once, no duplicates)
            // Note: unique() on 'name' already creates an index, so we don't need $table->index(['name'])
            $table->index(['generic_name']);
        });
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('pub.pharma_drugs');
    }
};
