<?php

use App\Http\Controllers\AuthController;
use App\Inventory\Http\Controllers\InventoryController;
use App\Orders\Http\Controllers\OrderController;
use App\Products\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('products', [ProductController::class, 'index']);
        Route::post('products', [ProductController::class, 'store']);
        Route::get('products/{id}', [ProductController::class, 'show']);
        Route::match(['put', 'patch'], 'products/{id}', [ProductController::class, 'update']);

        Route::get('orders', [OrderController::class, 'index']);
        Route::post('orders', [OrderController::class, 'store']);
        Route::get('orders/{id}', [OrderController::class, 'show']);
        Route::post('orders/{id}/pay', [OrderController::class, 'pay']);
        Route::post('orders/{id}/cancel', [OrderController::class, 'cancel']);

        Route::get('inventory/{productId}', [InventoryController::class, 'show']);
        Route::post('inventory/{productId}/adjust', [InventoryController::class, 'adjust']);
        Route::post('inventory/reserve', [InventoryController::class, 'reserve']);
    });
});



