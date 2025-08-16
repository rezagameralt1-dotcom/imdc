<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $roles = [
            ['id' => 1, 'name' => 'Admin', 'slug' => 'admin'],
            ['id' => 2, 'name' => 'User', 'slug' => 'user'],
            ['id' => 3, 'name' => 'Seller', 'slug' => 'seller'],
            ['id' => 4, 'name' => 'Designer', 'slug' => 'designer'],
            ['id' => 5, 'name' => 'LegalAdvisor', 'slug' => 'legaladvisor'],
            ['id' => 6, 'name' => 'Teacher', 'slug' => 'teacher'],
            ['id' => 7, 'name' => 'Auditor', 'slug' => 'auditor'],
        ];
        foreach ($roles as $r) {
            DB::table('roles')->updateOrInsert(['id' => $r['id']], ['name' => $r['name'], 'slug' => $r['slug']]);
        }

        $perms = [
            ['id' => 1, 'name' => 'Manage Users', 'slug' => 'manage_users'],
            ['id' => 2, 'name' => 'Manage Roles', 'slug' => 'manage_roles'],
            ['id' => 3, 'name' => 'View Reports', 'slug' => 'view_reports'],
            ['id' => 4, 'name' => 'Create Posts', 'slug' => 'create_posts'],
            ['id' => 5, 'name' => 'Manage Orders', 'slug' => 'manage_orders'],
        ];
        foreach ($perms as $p) {
            DB::table('permissions')->updateOrInsert(['id' => $p['id']], ['name' => $p['name'], 'slug' => $p['slug']]);
        }

        $rolePerm = [
            [1, 1], [1, 2], [1, 3], [1, 4], [1, 5], // Admin all
            [2, 4],                          // User: create posts
            [7, 3],                          // Auditor: view reports
        ];
        foreach ($rolePerm as [$role,$perm]) {
            DB::table('permission_role')->updateOrInsert(['role_id' => $role, 'permission_id' => $perm], []);
        }
    }
}
