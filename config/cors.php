<?php

return [
    'paths' => [
        'sanctum/csrf-cookie',
        'login-spa',
        'logout-spa',
        'me',
        'register-spa',
        'forgot-password',
        'reset-password',
        'api/*',
    ],
    'allowed_methods' => ['*'],
    'allowed_origins' => [env('FRONTEND_ORIGIN', 'http://digitalcity.test:5173')],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];

