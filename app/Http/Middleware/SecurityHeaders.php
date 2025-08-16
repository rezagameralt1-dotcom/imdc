<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $conf = config('security.headers', []);

        // Core security headers
        $this->set($response, 'X-Frame-Options', $conf['x_frame_options'] ?? 'DENY');
        $this->set($response, 'X-Content-Type-Options', $conf['x_content_type_options'] ?? 'nosniff');
        $this->set($response, 'Referrer-Policy', $conf['referrer_policy'] ?? 'strict-origin-when-cross-origin');
        $this->set($response, 'Permissions-Policy', $conf['permissions_policy'] ?? 'geolocation=()');

        if (! empty($conf['cross_origin_opener_policy'])) {
            $this->set($response, 'Cross-Origin-Opener-Policy', $conf['cross_origin_opener_policy']);
        }
        if (! empty($conf['cross_origin_resource_policy'])) {
            $this->set($response, 'Cross-Origin-Resource-Policy', $conf['cross_origin_resource_policy']);
        }
        if (! empty($conf['cross_origin_embedder_policy'])) {
            $this->set($response, 'Cross-Origin-Embedder-Policy', $conf['cross_origin_embedder_policy']);
        }

        // HSTS (HTTPS only!)
        $hsts = $conf['hsts'] ?? ['enable' => false];
        if (! empty($hsts['enable']) && $request->isSecure()) {
            $v = 'max-age='.(int) ($hsts['max_age'] ?? 15552000);
            if (! empty($hsts['include_subdomains'])) {
                $v .= '; includeSubDomains';
            }
            if (! empty($hsts['preload'])) {
                $v .= '; preload';
            }
            $this->set($response, 'Strict-Transport-Security', $v);
        }

        // CSP
        $csp = config('security.csp');
        if (! empty($csp['enable'])) {
            $header = ! empty($csp['report_only']) ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            $directives = $csp['directives'] ?? [];
            // Route overrides
            foreach (($csp['route_overrides'] ?? []) as $prefix => $over) {
                if ($request->is($prefix) || $request->is($prefix.'/*')) {
                    $directives = array_replace_recursive($directives, $over);
                    break;
                }
            }
            $value = $this->buildCsp($directives);
            if ($value) {
                $this->set($response, $header, $value);
            }
        }

        return $response;
    }

    private function set(Response $response, string $key, string $val): void
    {
        if (! $response->headers->has($key)) {
            $response->headers->set($key, $val);
        }
    }

    private function buildCsp(array $directives): string
    {
        $parts = [];
        foreach ($directives as $name => $sources) {
            if ($sources === null) {
                continue;
            }
            $srcs = array_values(array_filter((array) $sources, fn ($s) => $s !== null && $s !== ''));
            if (! count($srcs)) {
                continue;
            }
            $parts[] = $name.' '.implode(' ', $srcs);
        }

        return implode('; ', $parts);
    }
}
