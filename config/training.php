<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Training Feature Flag
    |--------------------------------------------------------------------------
    |
    | When FEATURE_TRAINING is false, training routes are not registered and
    | guardrail scripts will skip training tests.
    |
    */

    'enabled' => env('FEATURE_TRAINING', false),

];
