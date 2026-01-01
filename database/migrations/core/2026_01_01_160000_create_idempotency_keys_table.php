<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('core')->hasTable('idempotency_keys')) {
            Schema::connection('core')->create('idempotency_keys', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('scope', 64); // e.g., 'nfts.transfer'
                $table->string('key', 128); // The idempotency key value
                $table->string('request_hash', 64)->nullable(); // Hash of request payload for validation
                $table->unsignedSmallInteger('response_code'); // HTTP status code (200, 201, etc.)
                $table->text('response_body'); // JSON-encoded response body
                $table->string('resource_id')->nullable(); // UUID or ID of created resource
                $table->timestamps();

                // Unique index: one idempotency key per user per scope
                $table->unique(['user_id', 'scope', 'key'], 'idempotency_keys_user_scope_key_unique');
                $table->index(['user_id', 'scope']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('idempotency_keys');
    }
};
