<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create partial unique index: (requested_by_user_id, idempotency_key) WHERE idempotency_key IS NOT NULL
        // This ensures idempotency per user while allowing NULL values
        DB::connection('nfts')->statement('
            CREATE UNIQUE INDEX IF NOT EXISTS nfts_transfers_requested_by_user_id_idempotency_key_unique 
            ON nfts_transfers (requested_by_user_id, idempotency_key) 
            WHERE idempotency_key IS NOT NULL
        ');
    }

    public function down(): void
    {
        // Drop the unique index
        DB::connection('nfts')->statement('
            DROP INDEX IF EXISTS nfts_transfers_requested_by_user_id_idempotency_key_unique
        ');
    }
};
