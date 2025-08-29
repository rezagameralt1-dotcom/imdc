<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\AuthenticationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // هیچ ریدایرکتی برای مهمان نکن؛ خطای 401 بده
        $middleware->redirectGuestsTo(fn () => null);

        // اگر Auth هست و به صفحه مهمان رفت، جایی نبرش (اختیاری)
        $middleware->redirectUsersTo(fn () => null);

        // میان‌افزار نقش
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // هر چیزی زیر /api یا درخواست‌هایی که JSON می‌خوان
        // حتماً JSON برگردون (نه HTML و نه ریدایرکت)
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->is('api/*') || $request->expectsJson()
        );

        // به‌صورت صریح AuthenticationException را 401 JSON کن
        $exceptions->render(function (AuthenticationException $e, $request) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        });
    })
    ->create();
