<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        // اگر API است یا انتظار JSON دارد، redirect نکن
        if ($request->is('api/*') || $request->expectsJson()) {
            return null;
        }
        // برای وب اجازه‌ی هدایت به لاگین
        return route('login');
    }
}
