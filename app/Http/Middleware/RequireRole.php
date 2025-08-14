<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use App\Support\ApiResponse;

/**
 * Lightweight role guard without full auth.
 * Expects user context via header X-User-Id (or query user_id as fallback).
 * Example usage on routes:
 *   Route::middleware([RequireRole::class . ':admin'])->post(...)
 *   Route::middleware([RequireRole::class . ':seller,admin'])->patch(...)
 */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Identify user (from gateway/proxy). For demo: accept header or query param.
        $userId = (int) ($request->header('X-User-Id') ?: $request->query('user_id', 0));
        if ($userId <= 0) {
            return ApiResponse::error('Unauthenticated: missing X-User-Id', 401);
        }

        // User must exist
        $exists = DB::table('users')->where('id', $userId)->exists();
        if (!$exists) {
            return ApiResponse::error('Unauthenticated: user not found', 401);
        }

        // If no role required, just pass
        if (!$roles || count($roles) === 0) {
            return $next($request);
        }

        // Admin bypass
        $isAdmin = DB::table('role_user')
            ->join('roles','roles.id','=','role_user.role_id')
            ->where('role_user.user_id', $userId)
            ->where('roles.slug', 'admin')
            ->exists();
        if ($isAdmin) {
            // Inject resolved auth user id into request for downstream usage if needed
            $request->attributes->set('imdc_user_id', $userId);
            return $next($request);
        }

        // Check any of the required roles
        $allowed = DB::table('role_user')
            ->join('roles','roles.id','=','role_user.role_id')
            ->where('role_user.user_id', $userId)
            ->whereIn('roles.slug', $roles)
            ->exists();

        if (!$allowed) {
            return ApiResponse::error('Forbidden: insufficient role', 403);
        }

        $request->attributes->set('imdc_user_id', $userId);
        return $next($request);
    }
}
