<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdvancedCors
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('cors.enable', true)) {
            return $next($request);
        }

        [$profileName, $profile] = $this->pickProfile($request);
        $response = $request->isMethod('OPTIONS')
            ? response()->noContent(204)
            : $next($request);

        return $this->applyHeaders($request, $response, $profile);
    }

    private function pickProfile(Request $request): array
    {
        $path = ltrim($request->path(), '/');
        $routes = (array) config('cors.routes', []);
        foreach ($routes as $prefix => $name) {
            if ($path === $prefix || str_starts_with($path, rtrim($prefix, '/').'/')) {
                $prof = config("cors.profiles.$name") ?: config('cors.profiles.default');

                return [$name, $prof];
            }
        }

        return ['default', config('cors.profiles.default')];
    }

    private function applyHeaders(Request $request, Response $response, array $p): Response
    {
        $origin = $request->headers->get('Origin');
        $allowedOrigins = $p['origins'] ?? ['*'];
        $allowOrigin = '*';
        if (! in_array('*', $allowedOrigins, true) && $origin && $this->originAllowed($origin, $allowedOrigins)) {
            $allowOrigin = $origin;
        } elseif (! in_array('*', $allowedOrigins, true)) {
            // No wildcard and origin not matched: do not set CORS headers
            return $response;
        }

        $this->set($response, 'Access-Control-Allow-Origin', $allowOrigin);
        if (! empty($p['credentials'])) {
            $this->set($response, 'Access-Control-Allow-Credentials', 'true');
        }

        $allowMethods = implode(',', $p['methods'] ?? []);
        $allowHeaders = implode(',', $p['headers'] ?? []);
        $expose = implode(',', $p['expose'] ?? []);
        $maxAge = (string) (int) ($p['max_age'] ?? 600);

        $this->set($response, 'Access-Control-Allow-Methods', $allowMethods);
        $this->set($response, 'Access-Control-Allow-Headers', $allowHeaders);
        if ($expose) {
            $this->set($response, 'Access-Control-Expose-Headers', $expose);
        }
        $this->set($response, 'Access-Control-Max-Age', $maxAge);

        return $response;
    }

    private function originAllowed(string $origin, array $allowedOrigins): bool
    {
        foreach ($allowedOrigins as $ao) {
            if ($ao === $origin) {
                return true;
            }
            // Support wildcard suffix like https://*.example.com
            if (str_contains($ao, '*')) {
                $pattern = '#^'.str_replace('\*', '.*', preg_quote($ao, '#')).'$#i';
                if (preg_match($pattern, $origin)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function set(Response $res, string $key, string $val): void
    {
        if (! $res->headers->has($key)) {
            $res->headers->set($key, $val);
        }
    }
}
