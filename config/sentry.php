<?php
return [
    'dsn' => env('SENTRY_LARAVEL_DSN') ?: env('SENTRY_DSN'),
    'release' => env('SENTRY_RELEASE', null),
    'environment' => env('SENTRY_ENV', env('APP_ENV', 'production')),
    'send_default_pii' => false,
];
