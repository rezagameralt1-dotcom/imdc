<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'admin', 'description' => 'Platform administrator'],
            ['name' => 'manager', 'description' => 'Operations manager'],
            ['name' => 'customer', 'description' => 'Customer account'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], ['description' => $role['description']]);
        }

        $permissions = [
            'products.manage' => 'Manage product catalog',
            'orders.manage' => 'Manage orders',
            'inventory.manage' => 'Manage inventory',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(['name' => $name], ['description' => $description]);
        }

        $manager = Role::where('name', 'manager')->first();
        if ($manager) {
            $manager->permissions()->syncWithoutDetaching(
                Permission::whereIn('name', ['products.manage', 'orders.manage', 'inventory.manage'])->pluck('id')
            );
        }
    }
}
