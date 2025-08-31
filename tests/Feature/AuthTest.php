<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // اجرای مایگریشن برای هر تست روی دیتابیس تست
        $this->artisan('migrate');
    }

    public function test_ping_endpoint_is_ok()
    {
        $this->getJson('/api/ping')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_can_register_with_password_confirmation()
    {
        $payload = [
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];

        $this->postJson('/api/auth/register', $payload)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'demo@example.com');
    }

    public function test_login_me_logout_flow()
    {
        // ساخت کاربر
        $user = User::factory()->create([
            'email' => 'demo2@example.com',
            'password' => Hash::make('secret123'),
        ]);

        // login
        $login = $this->postJson('/api/auth/login', [
            'email' => 'demo2@example.com',
            'password' => 'secret123',
        ])->assertStatus(200)->json();

        $token = $login['data']['token'] ?? null;
        $this->assertNotEmpty($token, 'Token was not issued');

        // me
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.user.email', 'demo2@example.com');

        // logout
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')
            ->assertStatus(200);
    }
}
