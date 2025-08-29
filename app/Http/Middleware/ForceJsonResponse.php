<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->headers->has('Accept')) {
            $request->headers->set('Accept', 'application/json');
        }
        $response = $next($request);

        // تا جایی که ممکن است هدر JSON هم بگذاریم
        if (method_exists($response, 'headers')) {
            $response->headers->set('Content-Type', 'application/json; charset=utf-8');
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }

        return $response;
    }
}
