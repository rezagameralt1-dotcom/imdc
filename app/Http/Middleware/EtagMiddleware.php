<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EtagMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        if ($request->isMethod('GET') && $response->getStatusCode() === 200 && $response->headers->get('ETag') === null) {
            $etag = '"' . md5($response->getContent()) . '"';
            $response->headers->set('ETag', $etag);

            $ifNoneMatch = $request->headers->get('If-None-Match');
            if ($ifNoneMatch && trim($ifNoneMatch) === $etag) {
                $response->setNotModified();
            }
        }

        return $response;
    }
}

