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
        if (Schema::connection('core')->hasTable('pub.places')) {
            return;
        }

        Schema::connection('core')->create('pub.places', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('building')->comment('building, zone, landmark, etc.');
            $table->decimal('latitude', 10, 8)->comment('Latitude coordinate');
            $table->decimal('longitude', 11, 8)->comment('Longitude coordinate');
            $table->decimal('altitude', 10, 2)->nullable()->comment('Altitude in meters');
            $table->uuid('owner_did')->nullable()->comment('DID of place owner');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['type']);
            $table->index(['owner_did']);
            $table->index(['latitude', 'longitude']);

            // Foreign key to did_profiles if schema supports it
            try {
                $table->foreign('owner_did')->references('id')->on('did_profiles')->onDelete('set null');
            } catch (\Exception $e) {
                // FK may not be supported in this schema version, skip
            }
        });
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('pub.places');
    }
};
