<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_is_migrated(): void
    {
        $this->assertTrue(Schema::hasTable('migrations'));
    }
}
