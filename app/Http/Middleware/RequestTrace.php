<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestTrace
{
    public function handle(Request $request, Closure $next): Response
    {
        // Prefer incoming trace, otherwise generate
        $trace = $request->header('X-Trace-Id') ?: (string) Str::uuid();

        // Share trace in request for later usage
        $request->headers->set('X-Trace-Id', $trace);
        // Add to logging context
        Log::withContext(['trace_id' => $trace, 'path' => $request->path(), 'ip' => $request->ip()]);

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);
        $response->headers->set('X-Trace-Id', $trace);

        return $response;
    }
}
