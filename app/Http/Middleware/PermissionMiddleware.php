<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();
        if (!$user || !$user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Insufficient permission.'],
                'trace_id' => $request->header('X-Trace-Id') ?: null,
            ], 403);
        }
        return $next($request);
    }
}
