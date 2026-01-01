<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $enabled = match($feature) {
            'linking' => config('linking.enabled', false),
            'nft' => config('nft.enabled', false),
            'did' => env('FEATURE_DID', false),
            default => false,
        };

        if (!$enabled) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'FEATURE_DISABLED',
                    'message' => "Feature '{$feature}' is not enabled",
                ],
                'trace_id' => $request->attributes->get('trace_id'),
            ], 404);
        }

        return $next($request);
    }
}
