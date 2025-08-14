<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminFromEnvSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        if (!$email || !$password) {
            $this->command?->warn('ADMIN_EMAIL/ADMIN_PASSWORD not set; skipping AdminFromEnvSeeder.');
            return;
        }

        $user = DB::table('users')->where('email', $email)->first();
        if (!$user) {
            $id = DB::table('users')->insertGetId([
                'name' => 'Admin',
                'email' => $email,
                'password' => Hash::make($password),
                'is_admin' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->command?->info("Admin user created: {$email} (id={$id})");
        } else {
            DB::table('users')->where('id', $user->id)->update([
                'password' => Hash::make($password),
                'is_admin' => true,
                'is_active' => true,
                'updated_at' => now(),
            ]);
            $this->command?->info("Admin user updated: {$email}");
        }

        // Optional: attach 'admin' role if roles table exists
        try {
            if (Schema::hasTable('roles') && Schema::hasTable('role_user')) {
                $roleId = DB::table('roles')->where('name', 'admin')->value('id');
                $adminId = DB::table('users')->where('email', $email)->value('id');
                if ($roleId && $adminId) {
                    $exists = DB::table('role_user')->where(['role_id'=>$roleId,'user_id'=>$adminId])->exists();
                    if (!$exists) {
                        DB::table('role_user')->insert(['role_id'=>$roleId,'user_id'=>$adminId]);
                    }
                }
            }
        } catch (\Throwable $e) { /* ignore */ }
    }
}
