<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * Global middleware stack (for every request).
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // به جای نسخه‌های App\… از کلاس‌های هسته استفاده می‌کنیم تا خطا نگیری
        \Illuminate\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,

        // سفارشی تو — موقتاً غیرفعال برای تست خطای 500
        // \App\Http\Middleware\SecurityHeaders::class,
    ];

    /**
     * Route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            // به‌جای App\… از هسته استفاده می‌کنیم (اگر فایل‌های App\EncryptCookies و… را نداری)
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // اگر خواستی locale همیشه اعمال شود، این خط را آن‌کامنت کن:
            // \App\Http\Middleware\AppLocaleMiddleware::class,
        ],

        'api' => [
        'throttle:api',

        'throttle:api',

            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * Route middleware aliases.
     * (در لاراول 10 به‌جای $routeMiddleware از این استفاده کن)
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        // هر دو alias برای سازگاری
        'app.locale' => \App\Http\Middleware\AppLocaleMiddleware::class,
        'locale'     => \App\Http\Middleware\AppLocaleMiddleware::class,

        'auth'             => \App\Http\Middleware\Authenticate::class,
        'auth.basic'       => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'cache.headers'    => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can'              => \Illuminate\Auth\Middleware\Authorize::class,
        'guest'            => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'precognitive'     => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        'signed'           => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle'         => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified'         => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
    ];
}
