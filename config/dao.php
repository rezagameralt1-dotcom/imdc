<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DAO Feature Flag
    |--------------------------------------------------------------------------
    |
    | When FEATURE_DAO is false, DAO routes are not registered and
    | guardrail scripts will skip DAO tests.
    |
    */

    'enabled' => env('FEATURE_DAO', false),

];
