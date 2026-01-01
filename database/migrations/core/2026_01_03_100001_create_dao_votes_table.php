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
        if (Schema::connection('core')->hasTable('pub.dao_votes')) {
            return;
        }

        Schema::connection('core')->create('pub.dao_votes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('proposal_id')->comment('References pub.dao_proposals.id');
            $table->uuid('voter_did')->comment('DID of voter');
            $table->string('vote')->comment('yes, no, abstain');
            $table->integer('weight')->default(1)->comment('Voting weight (role-based)');
            $table->uuid('created_by')->nullable()->comment('User ID from core DB');
            $table->timestamps();

            // Unique constraint: one vote per DID per proposal
            $table->unique(['proposal_id', 'voter_did']);

            // Indexes (created once, no duplicates)
            // Note: unique constraint already creates index on (proposal_id, voter_did)
            $table->index(['proposal_id']);
            $table->index(['voter_did']);
            $table->index(['created_by']);

            // Foreign keys
            // Note: FK may create its own index, but we keep explicit indexes for query performance
            try {
                $table->foreign('proposal_id')->references('id')->on('pub.dao_proposals')->onDelete('cascade');
                $table->foreign('voter_did')->references('id')->on('did_profiles')->onDelete('restrict');
            } catch (\Exception $e) {
                // FK may not be supported in this schema version, skip
            }
        });
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('pub.dao_votes');
    }
};
