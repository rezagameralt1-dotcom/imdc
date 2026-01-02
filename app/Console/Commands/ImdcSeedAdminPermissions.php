<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

/**
 * Seed Admin Permissions
 * 
 * Idempotently creates baseline admin permissions with guard_name='api'
 */
class ImdcSeedAdminPermissions extends Command
{
    protected $signature = 'imdc:seed-admin-permissions';
    protected $description = 'Seed baseline admin permissions (idempotent, guard_name=api)';

    public function handle(): int
    {
        try {
            $guardName = 'api';
            $connection = config('permission.connection', 'core');
            
            // Ensure connection is set for Spatie models
            Permission::setConnection($connection);
            
            $permissions = [
                'admin.users.read',
                'admin.users.view',
                'admin.users.assign_roles',
                'admin.rbac.roles.read',
                'admin.rbac.permissions.read',
                'admin.rbac.permissions.assign',
                'reports.read',
                'audit.logs.read',
            ];
            
            $created = 0;
            $existing = 0;
            
            foreach ($permissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)
                    ->where('guard_name', $guardName)
                    ->first();
                
                if (!$permission) {
                    Permission::create([
                        'name' => $permissionName,
                        'guard_name' => $guardName,
                    ]);
                    $created++;
                    $this->line("Created: {$permissionName}");
                } else {
                    $existing++;
                }
            }
            
            if ($created > 0) {
                $this->info("Created {$created} new permissions");
            }
            if ($existing > 0) {
                $this->line("Skipped {$existing} existing permissions");
            }
            
            return 0;
        } catch (\Throwable $e) {
            fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
            return 1;
        }
    }
}
