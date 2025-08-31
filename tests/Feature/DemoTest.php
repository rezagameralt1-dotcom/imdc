<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_demo_hello()
    {
        $this->getJson('/api/demo/hello')
            ->assertStatus(200)
            ->assertJsonPath('ok', true);
    }

    public function test_demo_secure_requires_auth_and_then_passes_with_token()
    {
        // بدون توکن باید 401 یا 302 (بسته به تنظیمات) بده
        $this->getJson('/api/demo/secure')->assertStatus(401);

        // با توکن OK
        $user = User::factory()->create([
            'email' => 'u@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'u@example.com',
            'password' => 'secret123',
        ])->assertStatus(200)->json();

        $token = $login['data']['token'] ?? null;
        $this->withHeader('Authorization', 'Bearer '.$token)
             ->getJson('/api/demo/secure')
             ->assertStatus(200)
             ->assertJsonPath('ok', true);
    }
}
