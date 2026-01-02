<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only run if enrollments table exists
        if (!Schema::connection('core')->hasTable('pub.enrollments')) {
            return;
        }

        $connection = DB::connection('core');

        // Ensure did_id column exists and is nullable UUID
        try {
            $columnExists = $connection->selectOne("
                SELECT EXISTS (
                    SELECT FROM information_schema.columns 
                    WHERE table_schema = 'pub' 
                    AND table_name = 'enrollments' 
                    AND column_name = 'did_id'
                ) as exists
            ");

            if (!$columnExists || !$columnExists->exists) {
                $connection->statement("
                    ALTER TABLE pub.enrollments 
                    ADD COLUMN did_id UUID NULL
                ");
            }
        } catch (\Exception $e) {
            // Column may already exist, skip
        }

        // Drop existing FK if it exists (safe to run multiple times)
        try {
            $connection->statement("
                DO \$\$
                BEGIN
                    IF EXISTS (
                        SELECT 1 FROM information_schema.table_constraints 
                        WHERE constraint_schema = 'pub' 
                        AND table_name = 'enrollments' 
                        AND constraint_name LIKE '%did_id%'
                    ) THEN
                        ALTER TABLE pub.enrollments 
                        DROP CONSTRAINT IF EXISTS pub_enrollments_did_id_foreign CASCADE;
                    END IF;
                END \$\$;
            ");
        } catch (\Exception $e) {
            // Ignore if FK doesn't exist or drop fails
        }

        // Check if did_profiles exists in pub schema
        $didExistsPub = $connection->selectOne("
            SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'pub' AND table_name = 'did_profiles'
            ) as exists
        ");

        // Check if did_profiles exists in public schema
        $didExistsPublic = $connection->selectOne("
            SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' AND table_name = 'did_profiles'
            ) as exists
        ");

        // Add FK only if did_profiles exists
        if (($didExistsPub && $didExistsPub->exists) || ($didExistsPublic && $didExistsPublic->exists)) {
            try {
                if ($didExistsPub && $didExistsPub->exists) {
                    $connection->statement("
                        ALTER TABLE pub.enrollments 
                        ADD CONSTRAINT pub_enrollments_did_id_foreign 
                        FOREIGN KEY (did_id) REFERENCES pub.did_profiles(id) 
                        ON DELETE SET NULL
                    ");
                } elseif ($didExistsPublic && $didExistsPublic->exists) {
                    $connection->statement("
                        ALTER TABLE pub.enrollments 
                        ADD CONSTRAINT pub_enrollments_did_id_foreign 
                        FOREIGN KEY (did_id) REFERENCES did_profiles(id) 
                        ON DELETE SET NULL
                    ");
                }
            } catch (\Exception $e) {
                // FK may already exist or creation failed, skip
            }
        }
    }

    public function down(): void
    {
        if (!Schema::connection('core')->hasTable('pub.enrollments')) {
            return;
        }

        try {
            DB::connection('core')->statement("
                ALTER TABLE pub.enrollments 
                DROP CONSTRAINT IF EXISTS pub_enrollments_did_id_foreign CASCADE
            ");
        } catch (\Exception $e) {
            // Ignore if FK doesn't exist
        }
    }
};
