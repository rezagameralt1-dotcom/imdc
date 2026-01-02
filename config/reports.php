<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Reports Feature Flag
    |--------------------------------------------------------------------------
    |
    | When FEATURE_REPORTS is false, reports routes are not registered and
    | guardrail scripts will skip reports tests.
    |
    */

    'enabled' => env('FEATURE_REPORTS', false),

];
