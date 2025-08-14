<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// OpenAPI UI (Swagger & Redoc)
Route::get('/api/docs', function () {
    return view('docs.openapi');
});

// Sentry test route (only in local)
Route::get('/debug/sentry', function () {
    if (! app()->environment('local')) {
        abort(404);
    }
    throw new \RuntimeException('Sentry test exception from /debug/sentry');
});

Route::get('/', function () {
    return 'IMDC OK';
});
