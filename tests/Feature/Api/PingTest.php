<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class PingTest extends TestCase
{
    public function test_api_ping_returns_success_true(): void
    {
        // اول /api/ping رو تست می‌کنیم، اگه 404 بود /api/market/ping رو امتحان می‌کنیم
        $res = $this->getJson('/api/ping');
        if ($res->getStatusCode() !== 200) {
            $res = $this->getJson('/api/market/ping');
        }

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }
}
