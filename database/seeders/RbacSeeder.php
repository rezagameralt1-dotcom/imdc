<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['Admin','User','Seller'];
        $perms = [
            'system.view',
            'inventory.manage',
            'orders.read',
            'orders.write',
        ];

        // ساخت پرمیژن‌ها
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name'=>$p, 'guard_name'=>'web']);
        }

        // ساخت نقش‌ها و اتصال پرمیژن‌های پایه
        foreach ($roles as $r) {
            $role = Role::firstOrCreate(['name'=>$r, 'guard_name'=>'web']);
            if ($r === 'Admin') {
                $role->syncPermissions(Permission::all());
            } elseif ($r === 'Seller') {
                $role->syncPermissions([
                    'system.view','inventory.manage','orders.read','orders.write'
                ]);
            } else { // User
                $role->syncPermissions(['system.view']);
            }
        }

        // ساخت یک ادمین پیش‌فرض
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => Hash::make('secret123')]
        );
        $admin->assignRole('Admin');
    }
}
