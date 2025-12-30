<?php

return [
    'orders' => [
        // TTL رزرو سفارش (دقیقه)
        'reservation_ttl_minutes' => (int) env('IMDC_ORDER_RESERVATION_TTL_MINUTES', 15),

        // سقف تعداد سفارش‌هایی که هر بار expire می‌شوند
        'expire_batch_limit' => (int) env('IMDC_ORDER_RESERVATION_EXPIRE_BATCH_LIMIT', 500),
    ],
];
