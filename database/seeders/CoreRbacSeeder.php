<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class CoreRbacSeeder extends Seeder
{
    public function run(): void
    {
        $conn = DB::connection('pgsql');
        $now = now();

        // ---------- Roles ----------
        $roles = [
            ['name' => 'admin',    'description' => 'Platform administrator'],
            ['name' => 'manager',  'description' => 'Operations manager'],
            ['name' => 'customer', 'description' => 'Customer account'],
        ];

        foreach ($roles as $r) {
            $conn->table('roles')->updateOrInsert(
                ['name' => $r['name']],
                ['description' => $r['description'], 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $roleId = fn(string $name): int => (int) $conn->table('roles')->where('name', $name)->value('id');

        // ---------- Permissions ----------
        // NOTE: Policy برای products فقط products.manage را چک می‌کند.
        $permissions = [
            ['name' => 'products.manage',   'description' => 'Manage product catalog'],
            ['name' => 'orders.manage',     'description' => 'Manage orders'],
            ['name' => 'inventory.manage',  'description' => 'Manage inventory'],

            // Optional granular (فعلاً Policy استفاده نمی‌کند، ولی نگه می‌داریم)
            ['name' => 'products.view',     'description' => 'View products'],
            ['name' => 'products.create',   'description' => 'Create products'],
            ['name' => 'products.update',   'description' => 'Update products'],

            // NFT permissions
            ['name' => 'nft.mint',         'description' => 'Mint NFT tokens'],
            ['name' => 'nft.transfer',     'description' => 'Transfer NFT tokens'],
            ['name' => 'nft.read',         'description' => 'Read NFT tokens'],

            // DID permissions
            ['name' => 'did.manage.self',  'description' => 'Manage own DID profile'],
        ];

        foreach ($permissions as $p) {
            $conn->table('permissions')->updateOrInsert(
                ['name' => $p['name']],
                ['description' => $p['description'], 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $permId = fn(string $name): int => (int) $conn->table('permissions')->where('name', $name)->value('id');

        // ---------- Role ↔ Permission ----------
        // admin: همه manage ها + محصولات
        // manager: manage ها
        // customer: هیچ
        $adminRoleId   = $roleId('admin');
        $managerRoleId = $roleId('manager');

        $attach = function (int $roleId, array $permNames) use ($conn, $permId, $now): void {
            foreach ($permNames as $pn) {
                $pid = $permId($pn);
                if ($pid <= 0) continue;

                // avoid duplicates
                $exists = (bool) $conn->table('permission_role')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $pid)
                    ->exists();

                if (!$exists) {
                    $conn->table('permission_role')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $pid,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        };

        $attach($adminRoleId, [
            'products.manage', 'orders.manage', 'inventory.manage',
            'products.view', 'products.create', 'products.update',
            'nft.mint', 'nft.transfer', 'nft.read',
            'did.manage.self',
        ]);

        $attach($managerRoleId, [
            'products.manage', 'orders.manage', 'inventory.manage',
            'nft.read',
        ]);

        // ---------- Seed Admin User ----------
        // Production-safe pattern: admin is created via seeder, not via public register.
        $adminEmail = env('IMDC_ADMIN_EMAIL', 'admin@imdc.local');
        $adminPass  = env('IMDC_ADMIN_PASSWORD', 'ChangeMe123!'); // حتماً در .env عوض شود

        $conn->table('users')->updateOrInsert(
            ['email' => $adminEmail],
            [
                'name' => 'IMDC Admin',
                'password' => Hash::make($adminPass),
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $adminUserId = (int) $conn->table('users')->where('email', $adminEmail)->value('id');

        if ($adminUserId > 0) {
            $exists = (bool) $conn->table('role_user')
                ->where('user_id', $adminUserId)
                ->where('role_id', $adminRoleId)
                ->exists();

            if (!$exists) {
                $conn->table('role_user')->insert([
                    'user_id' => $adminUserId,
                    'role_id' => $adminRoleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
