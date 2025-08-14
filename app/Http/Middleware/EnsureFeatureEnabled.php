<?php
namespace App\Http\Middleware;

use App\Support\ApiResponse;
use App\Support\FeatureFlags;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    /**
     * Usage in routes:
     *   Route::middleware([EnsureFeatureEnabled::class . ':EXCHANGE'])->get(...);
     */
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        if (!FeatureFlags::enabled($featureKey, false)) {
            return ApiResponse::error("Feature '{$featureKey}' is disabled", 404);
        }
        return $next($request);
    }
}
