<?php

return [
    // global toggle
    'enable' => env('CORS_ENABLE', true),

    // named profiles
    'profiles' => [
        // پیش‌فرض (بدون کوکی)
        'default' => [
            'origins'     => array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOW_ORIGIN', '*')))),
            'methods'     => array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOW_METHODS', 'GET,POST,PUT,PATCH,DELETE,OPTIONS')))),
            'headers'     => array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOW_HEADERS', 'Content-Type,Authorization,X-Requested-With,X-Trace-Id,X-User-Id')))),
            'expose'      => array_filter(array_map('trim', explode(',', (string) env('CORS_EXPOSE_HEADERS', 'X-RateLimit-Limit,X-RateLimit-Remaining,Retry-After,Trace-Id')))),
            'credentials' => filter_var(env('CORS_ALLOW_CREDENTIALS', false), FILTER_VALIDATE_BOOLEAN),
            'max_age'     => (int) env('CORS_MAX_AGE', 600),
        ],

        // برای Sanctum/کوکی (origin دقیق و credentials=true)
        'stateful' => [
            'origins'     => array_filter(array_map('trim', explode(',', (string) env('CORS_STATEFUL_ORIGINS', '')))),
            'methods'     => ['GET','POST','PUT','PATCH','DELETE','OPTIONS'],
            'headers'     => ['Content-Type','Authorization','X-Requested-With','X-CSRF-TOKEN','X-Trace-Id'],
            'expose'      => ['Trace-Id'],
            'credentials' => true,
            'max_age'     => 600,
        ],

        // Docs/UI
        'docs' => [
            'origins'     => array_filter(array_map('trim', explode(',', (string) env('CORS_DOCS_ORIGINS', '*')))),
            'methods'     => ['GET','OPTIONS'],
            'headers'     => ['Content-Type','Authorization','X-Requested-With','X-Trace-Id'],
            'expose'      => ['Trace-Id'],
            'credentials' => false,
            'max_age'     => 300,
        ],

        // محدود (برای API عمومی در prod در صورت نیاز)
        'strict' => [
            'origins'     => array_filter(array_map('trim', explode(',', (string) env('CORS_STRICT_ORIGINS', '')))),
            'methods'     => ['GET','POST','OPTIONS'],
            'headers'     => ['Content-Type','Authorization','X-Trace-Id'],
            'expose'      => ['X-RateLimit-Limit','X-RateLimit-Remaining','Retry-After','Trace-Id'],
            'credentials' => false,
            'max_age'     => 600,
        ],
    ],

    // route prefix → profile (اولین مچ اعمال می‌شود) — ترتیب مهم است
    'routes' => [
        // احراز هویت SPA
        'spa-auth'           => env('CORS_PROFILE_SPA_AUTH', 'default'),

        // Sanctum/CSRF
        'sanctum'            => env('CORS_PROFILE_SANCTUM', 'stateful'),

        // مستندات و OpenAPI
        'api/health/openapi' => 'docs',
        'api/docs'           => 'docs',
        'docs'               => 'docs',
        'openapi'            => 'docs',

        // سلامت/متریک
        'api/metrics'        => 'strict',
        'api/health'         => 'default',

        // API عمومی
        'api'                => 'default',

        // (اختیاری) فول‌بک برای همه‌چیز
        // ''                 => 'default',
    ],
];
