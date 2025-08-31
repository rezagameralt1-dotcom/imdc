<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_admin_ping_and_user_cannot()
    {
        $this->artisan('migrate');

        // نقش‌ها
        Role::firstOrCreate(['name' => 'Admin']);
        Role::firstOrCreate(['name' => 'User']);

        // ادمین
        $admin = User::factory()->create([
            'email' => 'admin@test.local',
            'password' => Hash::make('secret123'),
        ]);
        $admin->syncRoles(['Admin']);

        // کاربر معمولی
        $user = User::factory()->create([
            'email' => 'user@test.local',
            'password' => Hash::make('secret123'),
        ]);
        $user->syncRoles(['User']);
        $user->syncPermissions(['system.view']);

        // توکن ادمین
        $adminLogin = $this->postJson('/api/auth/login', [
            'email' => 'admin@test.local',
            'password' => 'secret123',
        ])->assertStatus(200)->json('data');
        $adminToken = $adminLogin['token'];

        // توکن کاربر معمولی
        $userLogin = $this->postJson('/api/auth/login', [
            'email' => 'user@test.local',
            'password' => 'secret123',
        ])->assertStatus(200)->json('data');
        $userToken = $userLogin['token'];

        // Admin -> admin/ping = 200
        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->getJson('/api/admin/ping')
            ->assertStatus(200)
            ->assertJsonPath('data', 'admin-pong');

        // User -> admin/ping = 403
        $this->withHeader('Authorization', 'Bearer '.$userToken)
            ->getJson('/api/admin/ping')
            ->assertStatus(403);

        // User -> perm/ping = 200 (system.view دارد)
        $this->withHeader('Authorization', 'Bearer '.$userToken)
            ->getJson('/api/perm/ping')
            ->assertStatus(200)
            ->assertJsonPath('data', 'perm-pong');
    }
}
