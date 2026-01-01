<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pharma Advisor Feature Flag
    |--------------------------------------------------------------------------
    |
    | When FEATURE_PHARMA is false, pharma routes are not registered and
    | guardrail scripts will skip pharma tests.
    |
    */

    'enabled' => env('FEATURE_PHARMA', false),

    /*
    |--------------------------------------------------------------------------
    | Legal Disclaimer
    |--------------------------------------------------------------------------
    |
    | Mandatory disclaimer text included in all pharma API responses.
    |
    */

    'disclaimer' => env('PHARMA_DISCLAIMER', 'This information is for informational purposes only and does not constitute medical advice. Always consult with a qualified healthcare professional before making any medical decisions.'),

];
