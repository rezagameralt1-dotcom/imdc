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
        
        // Add guard_name to roles table if missing
        if (Schema::connection('core')->hasTable('roles')) {
            if (!Schema::connection('core')->hasColumn('roles', 'guard_name')) {
                Schema::connection('core')->table('roles', function (Blueprint $table) {
                    $table->string('guard_name', 50)->default('web')->after('name');
                });
                
                // Backfill existing rows with 'web'
                $connection->table('roles')->whereNull('guard_name')->update(['guard_name' => 'web']);
                
                // Make it NOT NULL after backfill
                Schema::connection('core')->table('roles', function (Blueprint $table) {
                    $table->string('guard_name', 50)->default('web')->nullable(false)->change();
                });
            }
        }
        
        // Add guard_name to permissions table if missing
        if (Schema::connection('core')->hasTable('permissions')) {
            if (!Schema::connection('core')->hasColumn('permissions', 'guard_name')) {
                Schema::connection('core')->table('permissions', function (Blueprint $table) {
                    $table->string('guard_name', 50)->default('web')->after('name');
                });
                
                // Backfill existing rows with 'web'
                $connection->table('permissions')->whereNull('guard_name')->update(['guard_name' => 'web']);
                
                // Make it NOT NULL after backfill
                Schema::connection('core')->table('permissions', function (Blueprint $table) {
                    $table->string('guard_name', 50)->default('web')->nullable(false)->change();
                });
            }
        }
    }

    public function down(): void
    {
        // Remove guard_name columns if they exist
        if (Schema::connection('core')->hasTable('roles') && Schema::connection('core')->hasColumn('roles', 'guard_name')) {
            Schema::connection('core')->table('roles', function (Blueprint $table) {
                $table->dropColumn('guard_name');
            });
        }
        
        if (Schema::connection('core')->hasTable('permissions') && Schema::connection('core')->hasColumn('permissions', 'guard_name')) {
            Schema::connection('core')->table('permissions', function (Blueprint $table) {
                $table->dropColumn('guard_name');
            });
        }
    }
};
