<?php

return [
    // Global defaults (per IP + route)
    'default' => [
        'limit_per_min' => (int) env('RATE_LIMIT_PER_MIN', 120),
        'burst' => (int) env('RATE_LIMIT_BURST', 60), // token bucket capacity
        'enabled' => env('RATE_LIMIT_ENABLE', true),
    ],

    // Route prefix overrides (first match wins)
    // Example: 'api/market' => ['limit_per_min' => 60, 'burst' => 30]
    'overrides' => [
        'api/health' => ['limit_per_min' => 300, 'burst' => 100],
        'api/metrics' => ['limit_per_min' => 120, 'burst' => 60],
        'api/docs' => ['limit_per_min' => 60,  'burst' => 30],
    ],

    // Exemptions (IP CIDR or exact IPs)
    'exempt_ips' => array_filter(array_map('trim', explode(',', (string) env('RATE_LIMIT_EXEMPT_IPS', '127.0.0.1')))),
];
