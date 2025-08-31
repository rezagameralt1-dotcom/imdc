<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class DevAdminSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $u = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => bcrypt('1')]
        );

        $role = Role::firstOrCreate(['name' => 'Admin','guard_name'=>'web']);
        $perm = Permission::firstOrCreate(['name'=>'system.view','guard_name'=>'web'], ['title'=>'System View']);

        $role->givePermissionTo($perm);
        $u->assignRole($role);
        $u->givePermissionTo($perm);
    }
}
