<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // همیشه کش پرمیژن‌ها را خالی کن تا تست‌ها ایزوله باشند
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // پرمیژن‌ها و نقش‌ها با guard 'sanctum'
        Permission::findOrCreate('system.view', 'sanctum');
        Permission::findOrCreate('orders.read', 'sanctum');
        Role::findOrCreate('Admin', 'sanctum');
        Role::findOrCreate('User', 'sanctum');

        // ساخت یوزرها
        $this->admin = User::factory()->create([
            'email' => 'admin@test.local',
            'password' => Hash::make('secret123'),
        ]);
        $this->user = User::factory()->create([
            'email' => 'user@test.local',
            'password' => Hash::make('secret123'),
        ]);

        // اتصال نقش‌ها و پرمیژن‌ها
        $this->admin->syncRoles(['Admin']);
        $this->admin->syncPermissions(['system.view', 'orders.read']);

        $this->user->syncRoles(['User']);
        $this->user->syncPermissions(['system.view']);
    }

    public function test_admin_can_access_admin_ping_and_user_cannot(): void
    {
        // Admin → 200 روی /api/admin/ping
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/ping')
            ->assertOk()
            ->assertJsonPath('success', true);

        // User → 403 روی /api/admin/ping
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/ping')
            ->assertForbidden();
    }

    public function test_user_with_permission_can_access_perm_ping(): void
    {
        // User که system.view دارد → 200 روی /api/perm/ping
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/perm/ping')
            ->assertOk()
            ->assertJsonPath('data', 'perm-pong');
    }

    public function test_guest_cannot_access_protected_routes(): void
    {
        // مهمان → 401 روی هر دو روت محافظت‌شده
        $this->getJson('/api/admin/ping')->assertUnauthorized();
        $this->getJson('/api/perm/ping')->assertUnauthorized();
    }
}
