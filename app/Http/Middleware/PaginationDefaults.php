<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaginationDefaults
{
    public function handle(Request $request, Closure $next): Response
    {
        $default = (int) config('imdc.pagination.default', 20);
        $max = (int) config('imdc.pagination.max', 100);

        // Normalize per_page in query string
        $per = (int) $request->query('per_page', $default);
        if ($per <= 0) {
            $per = $default;
        }
        if ($per > $max) {
            $per = $max;
        }

        // Mutate query for downstream paginators
        $request->query->set('per_page', $per);

        return $next($request);
    }
}
