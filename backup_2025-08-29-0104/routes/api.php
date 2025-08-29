<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PingController;

// پینگ عمومی
Route::get('/ping', PingController::class);

// احراز هویت (Sanctum)
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('token',    [AuthController::class, 'issueToken']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',      [AuthController::class, 'me']);
    });
});

// مسیرهای محافظت‌شده
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/secure/ping', fn () =>
        response()->json(['success'=>true,'data'=>['message'=>'secure pong'],'trace_id'=>uniqid()])
    );

    // فقط ادمین
    Route::get('/admin/ping', fn () =>
        response()->json(['success'=>true,'data'=>['message'=>'admin pong'],'trace_id'=>uniqid()])
    )->middleware('role:Admin');
});
