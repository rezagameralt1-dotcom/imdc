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

    public function test_admin_can_access_admin_ping_and_user_cannot(): void
    {
        $this->artisan('migrate');
        $this->seed(\Database\Seeders\RbacSeeder::class);

        // ادمین
        $admin = User::where('email','admin@example.com')->first();
        $adminToken = $admin->createToken('api')->plainTextToken;

        // کاربر معمولی
        $user = User::create([
            'name'=>'U1','email'=>'u1@example.com','password'=> Hash::make('secret123')
        ]);
        $user->assignRole('User');
        $userToken = $user->createToken('api')->plainTextToken;

        // Admin -> admin/ping OK
        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->getJson('/api/admin/ping')
            ->assertStatus(200)
            ->assertJsonPath('data','admin-pong');

        // User -> admin/ping 403
        $this->withHeader('Authorization', 'Bearer '.$userToken)
            ->getJson('/api/admin/ping')
            ->assertStatus(403);

        // User -> perm/ping OK (چون system.view دارد)
        $this->withHeader('Authorization', 'Bearer '.$userToken)
            ->getJson('/api/perm/ping')
            ->assertStatus(200)
            ->assertJsonPath('data','perm-pong');
    }
}
