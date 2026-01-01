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

        Schema::connection('nfts')->create('nfts_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('token_id');
            $table->uuid('from_user_id')->nullable();
            $table->uuid('to_user_id');
            $table->string('idempotency_key')->nullable();
            $table->uuid('requested_by_user_id');
            $table->enum('status', ['pending', 'committed', 'rejected'])->default('committed');
            $table->timestamps();

            $table->foreign('token_id')->references('id')->on('nfts_tokens')->cascadeOnDelete();
            $table->index('from_user_id');
            $table->index('to_user_id');
            $table->index('idempotency_key');
            $table->index('requested_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::connection('nfts')->dropIfExists('nfts_transfers');
    }
};
