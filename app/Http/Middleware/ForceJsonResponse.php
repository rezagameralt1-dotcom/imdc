<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        if ($response->headers) {
            $response->headers->set('Content-Type', 'application/json');
        }

        return $response;
    }
}
