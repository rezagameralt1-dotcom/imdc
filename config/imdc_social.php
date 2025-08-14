<?php

return [
    'rate_limits' => [
        'post_per_min' => env('IMDC_RATE_POST_PER_MIN', 30),
        'message_per_min' => env('IMDC_RATE_MESSAGE_PER_MIN', 60),
    ],
];
