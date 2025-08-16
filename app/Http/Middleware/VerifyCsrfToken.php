<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * اگر بخوای به‌جای withoutMiddleware، اینجا دائمی مستثنا کنی:
     */
    protected $except = [
        // 'spa-auth/*', // در صورت تمایل فعال کن
    ];
}
