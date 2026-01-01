<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure we use core connection
        $adminRole = Role::on('core')->firstOrCreate(['name' => 'Admin']);
        $userRole  = Role::on('core')->firstOrCreate(['name' => 'User']);

        $admin = User::on('core')->firstOrCreate(
            ['email' => 'admin@imdc.local'],
            ['name' => 'IMDC Admin', 'password' => Hash::make('Admin#12345')]
        );

        // Use Spatie's assignRole method (pass role name string, not model)
        if (!$admin->hasRole('Admin')) {
            $admin->assignRole('Admin');
        }
    }
}
