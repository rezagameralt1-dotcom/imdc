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

        Schema::connection('core')->create('pub.linking_events', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('event_type')->index(); // did_order_linked, did_nft_linked, order_nft_linked
            $table->jsonb('payload')->notNull();
            $table->uuid('actor_user_id')->nullable()->index();
            $table->string('trace_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            // Indexes for common queries
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('pub.linking_events');
    }
};
