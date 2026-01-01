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

        Schema::connection('core')->create('pub.dao_events', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('event_type');
            $table->jsonb('payload');
            $table->uuid('actor_did')->nullable()->index()->comment('DID of actor');
            $table->uuid('actor_user_id')->nullable()->index()->comment('User ID from core DB');
            $table->text('trace_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['event_type']);
            $table->index(['actor_did']);
            $table->index(['actor_user_id']);
            $table->index(['trace_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('pub.dao_events');
    }
};
