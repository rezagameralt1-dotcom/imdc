<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = $request->user();
        if (!$user || !method_exists($user, 'hasRole') || !$user->hasRole($role)) {
            return response()->json(['success'=>false,'error'=>'Forbidden','trace_id'=>uniqid()], 403);
        }
        return $next($request);
    }
}
