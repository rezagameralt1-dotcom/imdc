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
        
        // Add guard_name to roles table if missing
        if ($schema->hasTable('roles')) {
            if (!$schema->hasColumn('roles', 'guard_name')) {
                // Drop existing unique constraint on name only
                // Laravel creates unique indexes with pattern: {table}_{column}_unique
                try {
                    $connection->statement("ALTER TABLE roles DROP CONSTRAINT IF EXISTS roles_name_unique");
                } catch (\Exception $e) {
                    // Ignore if constraint doesn't exist
                }
                
                // Also try dropping any unique index on name
                try {
                    $connection->statement("DROP INDEX IF EXISTS roles_name_unique");
                } catch (\Exception $e) {
                    // Ignore if index doesn't exist
                }
                
                // Add guard_name column
                $schema->table('roles', function (Blueprint $table) {
                    $table->string('guard_name', 50)->default('web')->after('name');
                });
                
                // Backfill existing rows with 'web'
                $connection->table('roles')->update(['guard_name' => 'web']);
                
                // Make it NOT NULL after backfill
                $schema->table('roles', function (Blueprint $table) {
                    $table->string('guard_name', 50)->default('web')->nullable(false)->change();
                });
                
                // Add composite unique index on (name, guard_name)
                $schema->table('roles', function (Blueprint $table) {
                    $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
                });
            }
        }
        
        // Add guard_name to permissions table if missing
        if ($schema->hasTable('permissions')) {
            if (!$schema->hasColumn('permissions', 'guard_name')) {
                // Drop existing unique constraint on name only
                try {
                    $connection->statement("ALTER TABLE permissions DROP CONSTRAINT IF EXISTS permissions_name_unique");
                } catch (\Exception $e) {
                    // Ignore if constraint doesn't exist
                }
                
                // Also try dropping any unique index on name
                try {
                    $connection->statement("DROP INDEX IF EXISTS permissions_name_unique");
                } catch (\Exception $e) {
                    // Ignore if index doesn't exist
                }
                
                // Add guard_name column
                $schema->table('permissions', function (Blueprint $table) {
                    $table->string('guard_name', 50)->default('web')->after('name');
                });
                
                // Backfill existing rows with 'web'
                $connection->table('permissions')->update(['guard_name' => 'web']);
                
                // Make it NOT NULL after backfill
                $schema->table('permissions', function (Blueprint $table) {
                    $table->string('guard_name', 50)->default('web')->nullable(false)->change();
                });
                
                // Add composite unique index on (name, guard_name)
                $schema->table('permissions', function (Blueprint $table) {
                    $table->unique(['name', 'guard_name'], 'permissions_name_guard_name_unique');
                });
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('core');
        
        // Drop composite unique indexes first
        if ($schema->hasTable('roles')) {
            try {
                $schema->table('roles', function (Blueprint $table) {
                    $table->dropUnique('roles_name_guard_name_unique');
                });
            } catch (\Exception $e) {
                // Ignore if doesn't exist
            }
        }
        
        if ($schema->hasTable('permissions')) {
            try {
                $schema->table('permissions', function (Blueprint $table) {
                    $table->dropUnique('permissions_name_guard_name_unique');
                });
            } catch (\Exception $e) {
                // Ignore if doesn't exist
            }
        }
        
        // Remove guard_name columns if they exist
        if ($schema->hasTable('roles') && $schema->hasColumn('roles', 'guard_name')) {
            $schema->table('roles', function (Blueprint $table) {
                $table->dropColumn('guard_name');
            });
            
            // Restore single-column unique on name
            $schema->table('roles', function (Blueprint $table) {
                $table->unique('name', 'roles_name_unique');
            });
        }
        
        if ($schema->hasTable('permissions') && $schema->hasColumn('permissions', 'guard_name')) {
            $schema->table('permissions', function (Blueprint $table) {
                $table->dropColumn('guard_name');
            });
            
            // Restore single-column unique on name
            $schema->table('permissions', function (Blueprint $table) {
                $table->unique('name', 'permissions_name_unique');
            });
        }
    }
};
