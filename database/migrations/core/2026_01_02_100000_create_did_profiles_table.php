<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('core')->create('did_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->unsignedBigInteger('user_id')->unique()->index();
            $table->string('did')->unique()->index(); // e.g. "did:imdc:<uuid>"
            $table->string('wallet_address')->nullable();
            $table->string('display_name')->nullable();
            $table->jsonb('metadata_json')->nullable();
            $table->timestamps();

            // Foreign key to users table
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('did_profiles');
    }
};
