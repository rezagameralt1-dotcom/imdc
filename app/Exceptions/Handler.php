<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $e)
    {
        $traceId = $request instanceof Request ? $request->attributes->get('trace_id') : null;
        $isDebug = $this->shouldIncludeDebugDetails();

        if ($e instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'message' => 'Validation failed',
                    'details' => $this->mergeDetails(['fields' => $e->errors()], $e, $isDebug),
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
                    'details' => $this->mergeDetails(null, $e, $isDebug),
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
                    'details' => $this->mergeDetails(null, $e, $isDebug),
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
                    'details' => $this->mergeDetails(null, $e, $isDebug),
                ],
                'trace_id' => $traceId,
            ], $e->getStatusCode(), $e->getHeaders());
        }

        return response()->json([
            'success' => false,
            'data' => null,
            'error' => [
                'message' => 'Server error',
                'details' => $this->mergeDetails(null, $e, $isDebug),
            ],
            'trace_id' => $traceId,
        ], 500);
    }

    private function shouldIncludeDebugDetails(): bool
    {
        return app()->environment('local') || filter_var(env('IMDC_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function mergeDetails(?array $base, Throwable $e, bool $withDebug): ?array
    {
        if (! $withDebug) {
            return $base;
        }

        $trace = $e->getTrace();
        $top = $trace[0] ?? [];

        $debug = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'top_frame' => array_filter([
                'file' => $top['file'] ?? null,
                'line' => $top['line'] ?? null,
                'class' => $top['class'] ?? null,
                'function' => $top['function'] ?? null,
            ]),
        ];

        if ($base === null) {
            return $debug;
        }

        return array_merge($base, ['debug' => $debug]);
    }
}


