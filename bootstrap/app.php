<?php

use App\Http\Middleware\AttachTraceId;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Spatie\Permission\Middlewares\PermissionMiddleware;
use Spatie\Permission\Middlewares\RoleMiddleware;
use Spatie\Permission\Middlewares\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            AttachTraceId::class,
            ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (ValidationException $e, $request): Response {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
                'trace_id' => $request->attributes->get('trace_id'),
            ], 422);
        });

        $exceptions->renderable(function (AuthenticationException $e, $request): Response {
            return response()->json([
                'message' => 'Unauthenticated.',
                'trace_id' => $request->attributes->get('trace_id'),
            ], 401);
        });

        $exceptions->renderable(function (AuthorizationException $e, $request): Response {
            return response()->json([
                'message' => $e->getMessage() ?: 'Forbidden',
                'trace_id' => $request->attributes->get('trace_id'),
            ], 403);
        });

        $exceptions->renderable(function (HttpExceptionInterface $e, $request): Response {
            return response()->json([
                'message' => $e->getMessage() ?: Response::$statusTexts[$e->getStatusCode()] ?? 'Error',
                'trace_id' => $request->attributes->get('trace_id'),
            ], $e->getStatusCode(), $e->getHeaders());
        });
    })
    ->create();
