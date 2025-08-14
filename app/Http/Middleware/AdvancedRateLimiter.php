<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token-bucket Rate Limiter (per IP + route)
 * - Capacity = burst
 * - Refill rate = limit_per_min / 60 per second
 * - Adds headers: X-RateLimit-Limit, X-RateLimit-Remaining, Retry-After
 */
class AdvancedRateLimiter
{
    public function handle(Request $request, Closure $next): Response
    {
        $conf = config('rate.default');
        if (empty($conf['enabled'])) {
            return $next($request);
        }

        $ip = $request->ip() ?: '0.0.0.0';
        if ($this->isExemptIp($ip)) {
            return $next($request);
        }

        [$limit, $burst] = $this->limitsForRoute($request);

        // Build a stable key: ip + route prefix (first 2 segments)
        $path = ltrim($request->path(), '/');
        $segments = explode('/', $path, 3);
        $scope = implode('/', array_slice($segments, 0, 2));
        $scope = $scope ?: $path ?: 'root';
        $key = "rl:{$ip}:{$scope}";

        $now = microtime(true);
        $state = Cache::store()->get($key, null);
        if (! $state) {
            $state = ['tokens' => $burst, 'ts' => $now];
        }

        // Refill tokens
        $rate_per_sec = max(0.0, $limit / 60.0);
        $elapsed = max(0.0, $now - (float) ($state['ts'] ?? $now));
        $tokens = min($burst, (float) ($state['tokens'] ?? 0) + $elapsed * $rate_per_sec);

        $allowed = $tokens >= 1.0;
        if ($allowed) {
            $tokens -= 1.0;
            $state = ['tokens' => $tokens, 'ts' => $now];
            // store with TTL of one minute to expire idle keys
            Cache::store()->put($key, $state, 65);
        }

        // Headers
        $remaining = (int) floor($tokens);
        $headers = [
            'X-RateLimit-Limit' => (string) $limit,
            'X-RateLimit-Remaining' => (string) $remaining,
        ];

        if (! $allowed) {
            // compute wait time to next token
            $need = 1.0 - $tokens;
            $retry_after = (int) ceil($need / ($rate_per_sec ?: 0.000001));
            $headers['Retry-After'] = (string) $retry_after;

            return response()->json([
                'success' => false,
                'error' => 'Too Many Requests',
                'trace_id' => $request->headers->get('X-Trace-Id') ?: '',
            ], 429, $headers);
        }

        /** @var Response $response */
        $response = $next($request);
        foreach ($headers as $k => $v) {
            if (! $response->headers->has($k)) {
                $response->headers->set($k, $v);
            }
        }

        return $response;
    }

    private function limitsForRoute(Request $request): array
    {
        $limit = (int) config('rate.default.limit_per_min', 120);
        $burst = (int) config('rate.default.burst', 60);

        $path = ltrim($request->path(), '/');
        foreach ((array) config('rate.overrides', []) as $prefix => $cfg) {
            if ($path === $prefix || str_starts_with($path, rtrim($prefix, '/').'/')) {
                $limit = (int) ($cfg['limit_per_min'] ?? $limit);
                $burst = (int) ($cfg['burst'] ?? $burst);
                break;
            }
        }

        return [$limit, $burst];
    }

    private function isExemptIp(string $ip): bool
    {
        $ex = (array) config('rate.exempt_ips', []);
        foreach ($ex as $pattern) {
            if ($pattern === $ip) {
                return true;
            }
            // simple CIDR (only /8,/16,/24 supported quickly)
            if (preg_match('#^(\d+)\.(\d+)\.(\d+)\.(\d+)/(8|16|24)$#', $pattern, $m)) {
                [$all,$a,$b,$c,$d,$mask] = $m;
                $ipParts = explode('.', $ip);
                if ($mask == '8' && $ipParts[0] == $a) {
                    return true;
                }
                if ($mask == '16' && $ipParts[0] == $a && $ipParts[1] == $b) {
                    return true;
                }
                if ($mask == '24' && $ipParts[0] == $a && $ipParts[1] == $b && $ipParts[2] == $c) {
                    return true;
                }
            }
        }

        return false;
    }
}
