<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CspHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $resp = $next($request);

        $dev = app()->environment('local');
        $origin = config('app.url', 'http://digitalcity.test');
        $host = parse_url($origin, PHP_URL_HOST) ?: 'digitalcity.test';
        $vite = $host.':5173';

        $scriptSrc = "'self' 'unsafe-inline' https://unpkg.com".($dev ? " http://$vite" : '');
        $styleSrc = "'self' 'unsafe-inline' https://unpkg.com";
        $imgSrc = "'self' data: blob:";
        $connect = "'self'".($dev ? " http://$vite ws://$vite" : '');

        $csp = "default-src 'self'; script-src $scriptSrc; style-src $styleSrc; img-src $imgSrc; font-src 'self' data:; connect-src $connect; frame-ancestors 'none';";
        $resp->headers->set('Content-Security-Policy', $csp);

        return $resp;
    }
}
