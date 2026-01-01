<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Linking Feature Flag
    |--------------------------------------------------------------------------
    |
    | When FEATURE_LINKING is false, linking routes are not registered and
    | guardrail scripts will skip linking tests.
    |
    */

    'enabled' => env('FEATURE_LINKING', false),

];
