<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

/**
 * Seed Admin Permissions
 * 
 * Idempotently creates baseline admin permissions for both 'api' and 'web' guards
 */
class ImdcSeedAdminPermissions extends Command
{
    protected $signature = 'imdc:seed-admin-permissions';
    protected $description = 'Seed baseline admin permissions (idempotent, guards: api, web)';

    public function handle(): int
    {
        try {
            $guards = ['api', 'web'];
            $connection = config('permission.connection', 'core');
            
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
            
            foreach ($guards as $guardName) {
                foreach ($permissions as $permissionName) {
                    try {
                        // Use on() to specify connection, firstOrCreate for idempotency by (name, guard_name)
                        $permission = Permission::on($connection)->firstOrCreate(
                            [
                                'name' => $permissionName,
                                'guard_name' => $guardName,
                            ]
                        );
                        
                        if ($permission->wasRecentlyCreated) {
                            $created++;
                            $this->line("Created: {$permissionName} (guard: {$guardName})");
                        } else {
                            $existing++;
                        }
                    } catch (\Exception $e) {
                        // Handle race condition: if constraint violation, re-fetch
                        if (str_contains($e->getMessage(), 'unique') || str_contains($e->getMessage(), 'duplicate')) {
                            $permission = Permission::on($connection)
                                ->where('name', $permissionName)
                                ->where('guard_name', $guardName)
                                ->first();
                            
                            if ($permission) {
                                $existing++;
                            } else {
                                // Re-throw if it's not a constraint issue
                                throw $e;
                            }
                        } else {
                            throw $e;
                        }
                    }
                }
            }
            
            // Clear Spatie permission cache after seeding
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            
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
