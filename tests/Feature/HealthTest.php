<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_database_is_migrated(): void
    {
        // چون در CI قبل از تست‌ها migrate می‌کنیم، باید جدول migrations وجود داشته باشد
        $this->assertTrue(Schema::hasTable('migrations'));
    }
}
