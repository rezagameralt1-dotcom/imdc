<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSmokeTest extends TestCase {
    use RefreshDatabase;

    public function test_register_login_me_secure() {
        // دیتابیس تست
        $this->artisan('migrate');

        // ثبت‌نام
        $this->postJson('/api/auth/register', [
            'name' => 'Tester',
            'email' => 'tester@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertStatus(200);

        // ورود
        $login = $this->postJson('/api/auth/login', [
            'email' => 'tester@example.com',
            'password' => 'secret123',
        ])->assertStatus(200)->json();

        $token = $login['data']['token'] ?? '';

        // me
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.user.email', 'tester@example.com');

        // secure
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/demo/secure')
            ->assertStatus(200)
            ->assertJsonPath('user.email', 'tester@example.com');
    }
}
