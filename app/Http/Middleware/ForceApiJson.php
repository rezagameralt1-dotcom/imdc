<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceApiJson
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/*')) {
            // وادار کردن پاسخ/درخواست به JSON
            $request->headers->set('Accept', 'application/json');
        }
        return $next($request);
    }
}
