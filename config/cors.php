<?php
return [
    // Global toggle
    'enable' => env('CORS_ENABLE', true),

    // Named profiles for different surfaces
    'profiles' => [
        'default' => [
            'origins' => array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOW_ORIGIN', '*')))),
            'methods' => array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOW_METHODS', 'GET,POST,PUT,PATCH,DELETE,OPTIONS')))),
            'headers' => array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOW_HEADERS', 'Content-Type,Authorization,X-Requested-With,X-Trace-Id,X-User-Id')))),
            'expose'  => array_filter(array_map('trim', explode(',', (string) env('CORS_EXPOSE_HEADERS', 'X-RateLimit-Limit,X-RateLimit-Remaining,Retry-After,Trace-Id')))),
            'credentials' => filter_var(env('CORS_ALLOW_CREDENTIALS', false), FILTER_VALIDATE_BOOLEAN),
            'max_age' => (int) env('CORS_MAX_AGE', 600),
        ],
        // More strict (prod public APIs)
        'strict' => [
            'origins' => array_filter(array_map('trim', explode(',', (string) env('CORS_STRICT_ORIGINS', '')))),
            'methods' => ['GET','POST','OPTIONS'],
            'headers' => ['Content-Type','Authorization','X-Trace-Id'],
            'expose'  => ['X-RateLimit-Limit','X-RateLimit-Remaining','Retry-After','Trace-Id'],
            'credentials' => false,
            'max_age' => 600,
        ],
        // Docs (allow CDNs if UI loads assets)
        'docs' => [
            'origins' => array_filter(array_map('trim', explode(',', (string) env('CORS_DOCS_ORIGINS', '*')))),
            'methods' => ['GET','OPTIONS'],
            'headers' => ['Content-Type','Authorization','X-Requested-With','X-Trace-Id'],
            'expose'  => ['Trace-Id'],
            'credentials' => false,
            'max_age' => 300,
        ],
    ],

    // Route prefix → profile name (first match wins)
    'routes' => [
        'api/docs'   => 'docs',
        'api/health' => 'default',
        'api/metrics'=> 'strict',
        'api'        => 'default',
    ],
];
