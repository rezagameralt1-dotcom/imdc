<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();
        if (!$user || empty($roles) || !$user->hasAnyRole($roles)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Insufficient role.'],
                'trace_id' => $request->header('X-Trace-Id') ?: null,
            ], 403);
        }
        return $next($request);
    }
}
