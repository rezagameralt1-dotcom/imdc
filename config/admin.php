<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Panel Feature Flag
    |--------------------------------------------------------------------------
    |
    | When FEATURE_ADMIN is false, admin user management routes are not registered
    | and guardrail scripts will skip admin users tests.
    |
    */

    'enabled' => env('FEATURE_ADMIN', false),

];
