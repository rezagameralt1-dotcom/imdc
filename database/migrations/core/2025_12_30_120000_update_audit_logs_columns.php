<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE audit_logs ALTER COLUMN action TYPE varchar(128)');
        DB::statement('ALTER TABLE audit_logs ALTER COLUMN auditable_type TYPE varchar(255)');
        DB::statement('ALTER TABLE audit_logs ALTER COLUMN auditable_id TYPE varchar(36)');
        DB::statement('ALTER TABLE audit_logs ALTER COLUMN trace_id TYPE varchar(36)');
        DB::statement('ALTER TABLE audit_logs ALTER COLUMN payload TYPE jsonb USING to_jsonb(payload)');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getName() !== 'pgsql') {
            return;
        }

        // reverting types to previous broader forms
        DB::statement('ALTER TABLE audit_logs ALTER COLUMN action TYPE varchar(255)');
        DB::statement('ALTER TABLE audit_logs ALTER COLUMN auditable_type TYPE varchar(255)');
        DB::statement('ALTER TABLE audit_logs ALTER COLUMN auditable_id TYPE varchar(255)');
        DB::statement('ALTER TABLE audit_logs ALTER COLUMN trace_id TYPE varchar(255)');
        DB::statement('ALTER TABLE audit_logs ALTER COLUMN payload TYPE json USING payload::json');
    }
};


