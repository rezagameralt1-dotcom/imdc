<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Inventory\Http\Controllers\InventoryController;
use App\Orders\Http\Controllers\OrderController;
use App\Products\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Health check endpoint (no auth required for monitoring)
    Route::get('health/db', [HealthController::class, 'db']);
    
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::prefix('market')->group(function () {
            Route::get('products', [ProductController::class, 'index']);
            Route::post('products', [ProductController::class, 'store']);
        });

        // User info endpoint
        if (class_exists(\App\Http\Controllers\Api\MeController::class)) {
            Route::get('me', [\App\Http\Controllers\Api\MeController::class]);
        } elseif (class_exists(\App\Http\Controllers\MeController::class)) {
            Route::get('me', [\App\Http\Controllers\MeController::class]);
        } else {
            Route::get('me', function () {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'ME_CONTROLLER_MISSING',
                        'message' => 'MeController not installed'
                    ],
                    'trace_id' => 'local'
                ], 501);
            });
        }
        Route::get('auth/me', [AuthController::class, 'me']); // Keep for backward compatibility
        
        // RBAC-protected health endpoints
        // Admin ping: requires Admin role only (LOCKED - stable baseline)
        if (class_exists(\App\Http\Controllers\Api\AdminPingController::class)) {
            Route::get('admin/ping', [\App\Http\Controllers\Api\AdminPingController::class])
                ->middleware('role:Admin');
        }
        // Access policy: Admin OR Auditor (locked).
        if (class_exists(\App\Http\Controllers\Api\AuditPingController::class)) {
            Route::get('audit/ping', [\App\Http\Controllers\Api\AuditPingController::class])
                ->middleware('role:Admin,Auditor');
        }
        
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

Route::middleware(['auth:sanctum'])->prefix('market')->group(function () {
    Route::get('products', [ProductController::class, 'index']);
    Route::post('products', [ProductController::class, 'store']);
    
    Route::post('orders', [OrderController::class, 'storeMarket']);
    Route::get('orders/{id}', [OrderController::class, 'showMarket']);
});

// RBAC smoke-test endpoints (without /v1 prefix)
Route::middleware('auth:sanctum')->group(function () {
    if (class_exists(\App\Http\Controllers\Api\MeController::class)) {
        Route::get('/me', \App\Http\Controllers\Api\MeController::class);
    } elseif (class_exists(\App\Http\Controllers\MeController::class)) {
        Route::get('/me', \App\Http\Controllers\MeController::class);
    } else {
        Route::get('/me', function () {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ME_CONTROLLER_MISSING',
                    'message' => 'MeController not installed'
                ],
                'trace_id' => 'local'
            ], 501);
        });
    }
    // Admin ping: requires Admin role only (LOCKED - stable baseline)
    if (class_exists(\App\Http\Controllers\Api\AdminPingController::class)) {
        Route::get('/admin/ping', \App\Http\Controllers\Api\AdminPingController::class)
            ->middleware('role:Admin');
    }
    // Access policy: Admin OR Auditor (locked).
    if (class_exists(\App\Http\Controllers\Api\AuditPingController::class)) {
        Route::get('/audit/ping', \App\Http\Controllers\Api\AuditPingController::class)
            ->middleware('role:Admin,Auditor');
    }
});




