<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NoIndexOnStaging
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only apply on non-production envs
        if (app()->environment() !== 'production') {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow', false);
        }

        return $response;
    }
}

