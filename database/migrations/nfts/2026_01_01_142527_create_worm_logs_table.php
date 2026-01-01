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

        Schema::connection('nfts')->create('worm_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('event_type'); // mint, transfer, lease, etc.
            $table->string('entity_type');
            $table->uuid('entity_id');
            $table->jsonb('payload_json');
            $table->string('prev_hash')->default('');
            $table->string('hash')->nullable(); // Allow NULL initially, will be set after insert
            $table->timestamp('created_at');

            $table->index('event_type');
            $table->index(['entity_type', 'entity_id']);
            $table->index('prev_hash');
            $table->index('hash');
        });

        // Create trigger to prevent updates/deletes (WORM: Write-Once-Read-Many)
        // This enforces append-only behavior at the database level
        // Exception: Allow hash updates (for initial hash calculation after insert)
        DB::connection('nfts')->statement('
            CREATE OR REPLACE FUNCTION prevent_worm_log_modification()
            RETURNS TRIGGER AS $$
            BEGIN
                IF TG_OP = \'UPDATE\' THEN
                    -- Allow hash updates only (for initial hash calculation)
                    -- Check if only hash field changed and other fields are unchanged
                    IF (OLD.hash IS NULL OR OLD.hash = \'\') AND NEW.hash IS NOT NULL AND NEW.hash != \'\' THEN
                        -- Verify other fields haven\'t changed
                        IF OLD.event_type = NEW.event_type AND
                           OLD.entity_type = NEW.entity_type AND
                           OLD.entity_id = NEW.entity_id AND
                           OLD.payload_json = NEW.payload_json AND
                           (OLD.prev_hash IS NOT DISTINCT FROM NEW.prev_hash) AND
                           OLD.created_at = NEW.created_at THEN
                            RETURN NEW;
                        END IF;
                    END IF;
                    RAISE EXCEPTION \'worm_logs table is append-only: updates are not allowed\';
                END IF;
                IF TG_OP = \'DELETE\' THEN
                    RAISE EXCEPTION \'worm_logs table is append-only: deletes are not allowed\';
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::connection('nfts')->statement('
            CREATE TRIGGER worm_logs_no_update_delete
            BEFORE UPDATE OR DELETE ON worm_logs
            FOR EACH ROW
            EXECUTE FUNCTION prevent_worm_log_modification();
        ');
    }

    public function down(): void
    {
        DB::connection('nfts')->statement('DROP TRIGGER IF EXISTS worm_logs_no_update_delete ON worm_logs');
        DB::connection('nfts')->statement('DROP FUNCTION IF EXISTS prevent_worm_log_modification()');
        Schema::connection('nfts')->dropIfExists('worm_logs');
    }
};
