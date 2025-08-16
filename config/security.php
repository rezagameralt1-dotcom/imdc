<?php

return [
    'csp' => [
        'enable' => env('SECURITY_CSP_ENABLE', true),
        'report_only' => env('SECURITY_CSP_REPORT_ONLY', false),
        // Defaults are safe for API + Blade (adjust for your frontend as needed)
        'directives' => [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'frame-ancestors' => [env('SECURITY_ALLOWED_FRAME_ANCESTORS', "'none'")],
            'script-src' => [
                "'self'",
                // inline is off by default; enable with env if you must
            ],
            'style-src' => [
                "'self'",
                env('SECURITY_CSP_ALLOW_INLINE_STYLE', false) ? "'unsafe-inline'" : null,
            ],
            'img-src' => [
                "'self'",
                'data:',
                // extra image domains (comma-separated)
                ...array_filter(array_map('trim', explode(',', (string) env('SECURITY_ALLOWED_IMG_DOMAINS', '')))),
            ],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => [
                "'self'",
                // Add your API/WS origins if different
                env('APP_URL', 'http://localhost'),
            ],
            'object-src' => ["'none'"],
            'media-src' => ["'self'"],
            'frame-src' => ["'none'"],
            'worker-src' => ["'self'"],
            'manifest-src' => ["'self'"],
            // Only set if you have a report endpoint or external collector
            'report-uri' => env('SECURITY_CSP_REPORT_URI', null),
        ],
        // Route prefixes that need looser CSP (e.g., Swagger/Redoc docs)
        'route_overrides' => [
            'api/docs' => [
                'script-src' => [
                    "'self'",
                    'https://unpkg.com',
                    'https://cdn.redoc.ly',
                ],
                'style-src' => [
                    "'self'",
                    "'unsafe-inline'",
                ],
                'frame-ancestors' => ["'self'"],
            ],
        ],
    ],
    'headers' => [
        'x_frame_options' => env('SECURITY_X_FRAME_OPTIONS', 'DENY'),
        'x_content_type_options' => 'nosniff',
        'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'permissions_policy' => env('SECURITY_PERMISSIONS_POLICY', 'geolocation=(), microphone=(), camera=(), payment=(), usb=()'),
        'cross_origin_opener_policy' => env('SECURITY_COOP', 'same-origin'),
        'cross_origin_resource_policy' => env('SECURITY_CORP', 'same-origin'),
        'cross_origin_embedder_policy' => env('SECURITY_COEP', null), // e.g., require-corp
        'hsts' => [
            'enable' => env('SECURITY_HSTS_ENABLE', false),
            'max_age' => env('SECURITY_HSTS_MAXAGE', 15552000), // 180 days
            'include_subdomains' => env('SECURITY_HSTS_SUBS', false),
            'preload' => env('SECURITY_HSTS_PRELOAD', false),
        ],
    ],
];
