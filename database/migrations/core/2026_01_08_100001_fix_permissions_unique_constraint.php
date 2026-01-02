<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection('core');
        $schema = Schema::connection('core');
        
        if (!$schema->hasTable('permissions')) {
            return;
        }
        
        // Check if guard_name column exists
        if (!$schema->hasColumn('permissions', 'guard_name')) {
            // If guard_name doesn't exist, the previous migration should have added it
            // Skip this migration if guard_name is missing
            return;
        }
        
        // Drop old single-column unique constraint/index if it exists
        // Try multiple common names
        $oldIndexNames = [
            'permissions_name_unique',
            'permissions_name_guard_name_unique', // In case it was created incorrectly
        ];
        
        foreach ($oldIndexNames as $indexName) {
            // Check if constraint exists
            $constraintExists = $connection->select("
                SELECT 1 
                FROM pg_constraint 
                WHERE conname = ? 
                AND conrelid = 'permissions'::regclass
            ", [$indexName]);
            
            if (!empty($constraintExists)) {
                try {
                    $connection->statement("ALTER TABLE permissions DROP CONSTRAINT IF EXISTS {$indexName}");
                } catch (\Exception $e) {
                    // Ignore if already dropped
                }
            }
            
            // Check if index exists
            $indexExists = $connection->select("
                SELECT 1 
                FROM pg_indexes 
                WHERE tablename = 'permissions' 
                AND indexname = ?
                AND schemaname = 'public'
            ", [$indexName]);
            
            if (!empty($indexExists)) {
                try {
                    $connection->statement("DROP INDEX IF EXISTS public.{$indexName}");
                } catch (\Exception $e) {
                    // Ignore if already dropped
                }
            }
        }
        
        // Check if composite unique index already exists
        $compositeIndexExists = $connection->select("
            SELECT 1 
            FROM pg_indexes 
            WHERE tablename = 'permissions' 
            AND indexname = 'permissions_name_guard_name_unique'
            AND schemaname = 'public'
        ");
        
        if (empty($compositeIndexExists)) {
            // Create composite unique index on (name, guard_name)
            $schema->table('permissions', function (Blueprint $table) {
                $table->unique(['name', 'guard_name'], 'permissions_name_guard_name_unique');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('core');
        
        if (!$schema->hasTable('permissions')) {
            return;
        }
        
        // Drop composite unique index
        try {
            $schema->table('permissions', function (Blueprint $table) {
                $table->dropUnique('permissions_name_guard_name_unique');
            });
        } catch (\Exception $e) {
            // Ignore if doesn't exist
        }
        
        // Restore single-column unique (if needed for rollback)
        // Note: This may fail if duplicate names exist across guards
        try {
            $schema->table('permissions', function (Blueprint $table) {
                $table->unique('name', 'permissions_name_unique');
            });
        } catch (\Exception $e) {
            // Ignore if constraint violation (expected if duplicates exist)
        }
    }
};
