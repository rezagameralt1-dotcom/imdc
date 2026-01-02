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
        if (Schema::connection('core')->hasTable('pub.skill_nfts')) {
            return;
        }

        Schema::connection('core')->create('pub.skill_nfts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('course_id')->comment('FK to pub.courses.id');
            $table->uuid('user_id')->comment('FK to pub.users.id (if exists)');
            $table->uuid('did_id')->nullable();
            $table->string('nft_token_id', 128)->nullable()->comment('Reserved for future on-chain NFT token ID');
            $table->jsonb('metadata')->default('{}');
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamps();

            // Unique constraint: one skill NFT per user per course (idempotent issuance)
            $table->unique(['course_id', 'user_id']);

            $table->index(['course_id']);
            $table->index(['user_id']);
            $table->index(['did_id']);
            $table->index(['nft_token_id']);

            // Foreign keys
            try {
                $table->foreign('course_id')->references('id')->on('pub.courses')->onDelete('cascade');
                
                // Check if pub.users exists
                $usersExists = DB::connection('core')->selectOne("
                    SELECT EXISTS (
                        SELECT FROM information_schema.tables 
                        WHERE table_schema = 'pub' AND table_name = 'users'
                    ) as exists
                ");
                if ($usersExists && $usersExists->exists) {
                    $table->foreign('user_id')->references('id')->on('pub.users')->onDelete('restrict');
                }
            } catch (\Exception $e) {
                // FK may not be supported, skip
            }
        });
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('pub.skill_nfts');
    }
};
