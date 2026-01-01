<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Accounting Sync Enabled
    |--------------------------------------------------------------------------
    |
    | Controls whether accounting vouchers and ledger entries are created
    | when orders are paid. Defaults to true for local environment,
    | false for production unless explicitly enabled.
    |
    */
    'sync_enabled' => env('ACCOUNTING_SYNC_ENABLED', null),
];
