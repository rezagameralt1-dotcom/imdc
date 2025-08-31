<?php

use Illuminate\Support\Facades\Route;

// از کنترلر احراز هویت موجودِ پروژه استفاده می‌کنیم:
use App\Http\Controllers\Api\AuthController;

// دمو کنترلر (Codex اضافه کرده):
use App\Http\Controllers\DemoController;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::get('ping', fn() => response()->json([
    'success' => true,
    'data'    => ['pong' => true],
    'trace_id'=> null,
]));

// Auth (از کنترلر موجود شما که الان جواب می‌دهد)
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'issueToken']); // قبلاً همین کار می‌کرد
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',      [AuthController::class, 'me']);
    });
});

// Demo routes (عمومی و محافظت‌شده)
Route::get('demo/hello', [DemoController::class, 'hello']);
Route::middleware('auth:sanctum')->get('demo/secure', [DemoController::class, 'secure']);

/*
|--------------------------------------------------------------------------
| Protected (placeholder)
|--------------------------------------------------------------------------
| بلاک‌های NFT و Marketplace عمداً غیرفعال شدند تا وقتی کنترلرها آماده شد.
| اگر لازم شد، بعداً با شرط وجود کلاس‌ها می‌توانیم برگردانیم.
*/

/*
// ===== NFT (فعلاً غیرفعال چون کنترلر وجود ندارد) =====
// use App\Http\Controllers\Api\NFT\NFTController;
// Route::middleware('auth:sanctum')->prefix('nft')->group(function () {
//     Route::post('mint',     [NFTController::class, 'mint']);
//     Route::post('transfer', [NFTController::class, 'transfer']);
//     Route::post('burn',     [NFTController::class, 'burn']);
// });

// ===== Marketplace (فعلاً غیرفعال چون کنترلرها وجود ندارند) =====
// use App\Http\Controllers\Api\Marketplace\CategoryController;
// use App\Http\Controllers\Api\Marketplace\ProductController;
// use App\Http\Controllers\Api\Marketplace\InventoryController;
// use App\Http\Controllers\Api\Marketplace\OrderController;
// if (class_exists(\App\Http\Controllers\Api\Marketplace\CategoryController::class)) {
//     Route::middleware(['auth:sanctum'])->group(function () {
//         // Categories
//         Route::get('/market/categories', [CategoryController::class, 'index'])->middleware('permission:system.view');
//         // ...
//     });
// }
*/

// Fallback JSON 404
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'error'   => ['code' => 404, 'message' => 'API route not found'],
        'trace_id'=> null,
    ], 404);
});
