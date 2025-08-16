<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PiiSafeRequestLog
{
    public function handle(Request $request, Closure $next): Response
    {
        // Log only metadata; avoid bodies to keep PII out of logs
        Log::info('api.request', [
            'method' => $request->getMethod(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'agent' => substr((string) $request->userAgent(), 0, 120),
            'trace_id' => $request->header('X-Trace-Id'),
        ]);

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        Log::info('api.response', [
            'status' => $response->getStatusCode(),
            'path' => $request->path(),
            'trace_id' => $request->header('X-Trace-Id'),
        ]);

        return $response;
    }
}
