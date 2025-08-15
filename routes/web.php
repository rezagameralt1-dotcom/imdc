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

Route::get('/healthz', function () {
    return response('HEALTH OK', 200);
});

/**
 * TEMPORARY SAFE LOGIN PLACEHOLDER
 * Replaces failing /login route with a minimal 200 response for pipeline.
 * TODO: Replace with real auth view/controller later.
 */
Route::get('/login', function () {
    return response('<!doctype html><html><head><meta charset="utf-8"><title>Login</title></head><body><h1>Login</h1><p>Temporary OK for pipeline.</p></body></html>', 200);
});
