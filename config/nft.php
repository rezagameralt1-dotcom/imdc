<?php

return [
    /*
    |--------------------------------------------------------------------------
    | NFT Feature Flag
    |--------------------------------------------------------------------------
    |
    | When FEATURE_NFT is false, NFT routes are not registered and
    | guardrail scripts will skip NFT tests.
    |
    */

    'enabled' => env('FEATURE_NFT', false),

];
