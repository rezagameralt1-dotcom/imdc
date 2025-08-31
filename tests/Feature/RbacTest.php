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

    protected Role $adminRole;
    protected Role $userRole;

    protected Permission $permView;
    protected Permission $permOrdersRead;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // نقش/پرمیژن‌ها با guard 'web'
        $this->permView       = Permission::findOrCreate('system.view', 'web');
        $this->permOrdersRead = Permission::findOrCreate('orders.read', 'web');
        $this->adminRole      = Role::findOrCreate('Admin', 'web');
        $this->userRole       = Role::findOrCreate('User', 'web');

        // یوزرها
        $this->admin = User::factory()->create([
            'email' => 'admin@test.local',
            'password' => Hash::make('secret123'),
        ]);
        $this->user = User::factory()->create([
            'email' => 'user@test.local',
            'password' => Hash::make('secret123'),
        ]);

        // اتصال با نمونه‌ها (از mismatch جلوگیری می‌کند)
        $this->admin->syncRoles([$this->adminRole]);
        $this->admin->syncPermissions([$this->permView, $this->permOrdersRead]);

        $this->user->syncRoles([$this->userRole]);
        $this->user->syncPermissions([$this->permView]);
    }

    public function test_admin_can_access_admin_ping_and_user_cannot(): void
    {
        // Admin → 200
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/ping')
            ->assertOk()
            ->assertJsonPath('success', true);

        // User → 403
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/ping')
            ->assertForbidden();
    }

    public function test_user_with_permission_can_access_perm_ping(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/perm/ping')
            ->assertOk()
            ->assertJsonPath('data', 'perm-pong');
    }

    public function test_guest_cannot_access_protected_routes(): void
    {
        $this->getJson('/api/admin/ping')->assertUnauthorized();
        $this->getJson('/api/perm/ping')->assertUnauthorized();
    }
}
