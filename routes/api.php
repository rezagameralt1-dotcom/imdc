<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PublicApiV2Controller;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| These routes are loaded by the RouteServiceProvider and assigned the "api"
| middleware group & "api" prefix. Keep them stateless.
*/

Route::prefix('v1')->group(function () {
    Route::get('/posts', [PublicApiV2Controller::class, 'posts'])->middleware('throttle:api');
    Route::get('/pages', [PublicApiV2Controller::class, 'pages'])->middleware('throttle:api');
});

