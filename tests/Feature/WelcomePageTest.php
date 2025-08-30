<?php

namespace Tests\Feature;

use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    public function test_root_route_is_accessible(): void
    {
        $this->get('/')->assertStatus(200);
    }
}
