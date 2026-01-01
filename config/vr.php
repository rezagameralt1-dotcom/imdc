<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VR / 3D + Map Feature Flag
    |--------------------------------------------------------------------------
    |
    | When FEATURE_VR is false, places routes are not registered and
    | guardrail scripts will skip places tests.
    |
    */

    'enabled' => env('FEATURE_VR', false),

];
