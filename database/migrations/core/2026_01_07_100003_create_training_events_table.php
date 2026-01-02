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
        if (Schema::connection('core')->hasTable('pub.training_events')) {
            return;
        }

        Schema::connection('core')->create('pub.training_events', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('event_type', 64);
            $table->jsonb('payload')->default('{}');
            $table->uuid('actor_user_id')->nullable();
            $table->uuid('trace_id');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_type']);
            $table->index(['actor_user_id']);
            $table->index(['trace_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('pub.training_events');
    }
};
