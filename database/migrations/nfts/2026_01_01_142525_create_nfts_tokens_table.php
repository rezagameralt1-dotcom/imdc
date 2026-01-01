<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('nfts')->statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');

        Schema::connection('nfts')->create('nfts_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('contract');
            $table->string('token_id');
            $table->unsignedBigInteger('owner_user_id'); // No FK - users table is in core DB
            $table->string('metadata_uri')->nullable();
            $table->enum('status', ['minted', 'transferred', 'burned'])->default('minted');
            $table->timestamps();

            $table->index('owner_user_id');
            $table->index(['contract', 'token_id']);
            $table->unique(['contract', 'token_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('nfts')->dropIfExists('nfts_tokens');
    }
};
