<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Admin',        'title' => 'مدیر سیستم'],
            ['name' => 'User',         'title' => 'کاربر عادی'],
            ['name' => 'Seller',       'title' => 'فروشنده'],
            ['name' => 'Designer',     'title' => 'طراح'],
            ['name' => 'LegalAdvisor', 'title' => 'مشاور حقوقی'],
            ['name' => 'Teacher',      'title' => 'مدرس'],
            ['name' => 'Auditor',      'title' => 'ممیز'],
        ];
        foreach ($roles as $r) { Role::firstOrCreate(['name'=>$r['name']], ['title'=>$r['title']]); }

        $perms = [
            ['name' => 'system.view',      'title' => 'مشاهده وضعیت سیستم'],
            ['name' => 'users.manage',     'title' => 'مدیریت کاربران'],
            ['name' => 'orders.read',      'title' => 'خواندن سفارش‌ها'],
            ['name' => 'orders.write',     'title' => 'ویرایش سفارش‌ها'],
            ['name' => 'inventory.manage', 'title' => 'مدیریت موجودی'],
        ];
        foreach ($perms as $p) { Permission::firstOrCreate(['name'=>$p['name']], ['title'=>$p['title']]); }

        $adminRole = Role::where('name','Admin')->first();
        if ($adminRole) {
            $adminPerms = Permission::whereIn('name', array_column($perms,'name'))->pluck('id')->all();
            $adminRole->permissions()->syncWithoutDetaching($adminPerms);
        }

        if ($adminRole && ($u = User::where('email','admin@example.com')->first())) {
            $u->roles()->syncWithoutDetaching([$adminRole->id]);
        }
    }
}
