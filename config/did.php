<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DID Feature Flag
    |--------------------------------------------------------------------------
    |
    | When FEATURE_DID is false, DID routes are not registered and
    | guardrail scripts will skip DID tests.
    |
    */

    'enabled' => env('FEATURE_DID', false),

];
