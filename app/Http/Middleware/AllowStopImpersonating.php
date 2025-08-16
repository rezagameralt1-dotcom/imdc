<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowStopImpersonating
{
    public function handle(Request $request, Closure $next): Response
    {
        // If impersonating, allow through regardless of admin gate
        if (session()->has('impersonator_id')) {
            return $next($request);
        }

        // Otherwise, continue (admin gate already applied on route group)
        return $next($request);
    }
}
