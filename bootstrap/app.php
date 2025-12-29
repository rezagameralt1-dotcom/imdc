<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \App\Http\Middleware\AttachTraceId::class,
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, Request $request) {
            $traceId = $request->attributes->get('trace_id');

            if ($e instanceof ValidationException) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'error' => [
                        'message' => 'Validation failed',
                        'details' => $e->errors(),
                    ],
                    'trace_id' => $traceId,
                ], 422);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'error' => [
                        'message' => 'Unauthenticated',
                    ],
                    'trace_id' => $traceId,
                ], 401);
            }

            if ($e instanceof AuthorizationException) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'error' => [
                        'message' => 'Forbidden',
                    ],
                    'trace_id' => $traceId,
                ], 403);
            }

            if ($e instanceof HttpExceptionInterface) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'error' => [
                        'message' => $e->getMessage() ?: 'HTTP error',
                    ],
                    'trace_id' => $traceId,
                ], $e->getStatusCode(), $e->getHeaders());
            }

            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'message' => 'Server error',
                ],
                'trace_id' => $traceId,
            ], 500);
        });
    })
    ->create();
