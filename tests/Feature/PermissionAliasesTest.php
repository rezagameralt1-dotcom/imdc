<?php

namespace Tests\Feature;

use Tests\TestCase;

class PermissionAliasesTest extends TestCase
{
    public function test_spatie_middlewares_are_resolvable(): void
    {
        $this->assertTrue(class_exists(\Spatie\Permission\Middleware\PermissionMiddleware::class));
        $this->assertTrue(class_exists(\Spatie\Permission\Middleware\RoleMiddleware::class));
        $this->assertTrue(class_exists(\Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class));
    }
}
