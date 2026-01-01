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
        if (Schema::connection('core')->hasTable('pub.dao_proposals')) {
            return;
        }

        Schema::connection('core')->create('pub.dao_proposals', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('title');
            $table->text('description');
            $table->string('status')->default('draft')->comment('draft, active, closed, executed');
            $table->uuid('created_by_did')->comment('DID of proposal creator');
            $table->timestamp('voting_starts_at')->nullable();
            $table->timestamp('voting_ends_at')->nullable();
            $table->integer('quorum')->default(0)->comment('Minimum votes required');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Indexes (created once, no duplicates)
            $table->index(['status']);
            $table->index(['created_by_did']);
            $table->index(['voting_starts_at', 'voting_ends_at']);

            // Foreign key to did_profiles if schema supports it
            // Note: FK may create its own index, but we keep explicit index for query performance
            try {
                $table->foreign('created_by_did')->references('id')->on('did_profiles')->onDelete('restrict');
            } catch (\Exception $e) {
                // FK may not be supported in this schema version, skip
            }
        });
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('pub.dao_proposals');
    }
};
