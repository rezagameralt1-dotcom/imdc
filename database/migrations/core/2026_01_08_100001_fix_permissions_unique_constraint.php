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
        
        // Find and drop any unique constraint/index on permissions(name) only
        // Query pg_indexes to find unique indexes on just 'name'
        $singleColumnIndexes = $connection->select("
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE tablename = 'permissions'
            AND schemaname = 'public'
            AND indexdef LIKE '%UNIQUE%'
            AND indexdef LIKE '%(name)%'
            AND indexdef NOT LIKE '%guard_name%'
        ");
        
        foreach ($singleColumnIndexes as $index) {
            $indexName = $index->indexname;
            try {
                // Try dropping as constraint first
                $connection->statement("ALTER TABLE permissions DROP CONSTRAINT IF EXISTS {$indexName}");
            } catch (\Exception $e) {
                // If not a constraint, try dropping as index
                try {
                    $connection->statement("DROP INDEX IF EXISTS public.{$indexName}");
                } catch (\Exception $e2) {
                    // Ignore if already dropped
                }
            }
        }
        
        // Also try dropping by common name
        try {
            $connection->statement("ALTER TABLE permissions DROP CONSTRAINT IF EXISTS permissions_name_unique");
        } catch (\Exception $e) {
            // Ignore
        }
        try {
            $connection->statement("DROP INDEX IF EXISTS public.permissions_name_unique");
        } catch (\Exception $e) {
            // Ignore
        }
        
        // Check if composite unique index already exists
        $compositeIndexExists = $connection->select("
            SELECT 1 
            FROM pg_indexes 
            WHERE tablename = 'permissions' 
            AND indexname = 'permissions_name_guard_unique'
            AND schemaname = 'public'
        ");
        
        if (empty($compositeIndexExists)) {
            // Also check for alternative name
            $compositeIndexExistsAlt = $connection->select("
                SELECT 1 
                FROM pg_indexes 
                WHERE tablename = 'permissions' 
                AND (indexname = 'permissions_name_guard_name_unique' OR indexdef LIKE '%(name, guard_name)%')
                AND schemaname = 'public'
            ");
            
            if (empty($compositeIndexExistsAlt)) {
                // Create composite unique index on (name, guard_name)
                try {
                    $connection->statement("
                        CREATE UNIQUE INDEX IF NOT EXISTS permissions_name_guard_unique 
                        ON permissions (name, guard_name)
                    ");
                } catch (\Exception $e) {
                    // If CREATE UNIQUE INDEX IF NOT EXISTS fails, use Schema builder
                    try {
                        $schema->table('permissions', function (Blueprint $table) {
                            $table->unique(['name', 'guard_name'], 'permissions_name_guard_unique');
                        });
                    } catch (\Exception $e2) {
                        // Ignore if already exists
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('core');
        
        if (!$schema->hasTable('permissions')) {
            return;
        }
        
        $connection = DB::connection('core');
        
        // Drop composite unique index (try both possible names)
        try {
            $connection->statement("DROP INDEX IF EXISTS public.permissions_name_guard_unique");
        } catch (\Exception $e) {
            // Ignore
        }
        try {
            $connection->statement("DROP INDEX IF EXISTS public.permissions_name_guard_name_unique");
        } catch (\Exception $e) {
            // Ignore
        }
        try {
            $schema->table('permissions', function (Blueprint $table) {
                $table->dropUnique('permissions_name_guard_unique');
            });
        } catch (\Exception $e) {
            // Ignore if doesn't exist
        }
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
