<?php

use App\Http\Controllers\API\DidProfileController;
use App\Http\Controllers\API\HealthController;
use App\Http\Controllers\API\InventoryController;
use App\Http\Controllers\API\MessageController;
use App\Http\Controllers\API\MetricsController;
use App\Http\Controllers\API\NftTransferController;
use App\Http\Controllers\API\OpenApiController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\PostController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\ProgressController;
use App\Http\Controllers\API\RBACController;
use App\Http\Controllers\API\SafeRoomController;
use App\Http\Controllers\API\ShopController;
use App\Http\Controllers\API\WalletController;
use App\Http\Middleware\AdvancedCors;
use App\Http\Middleware\AdvancedRateLimiter;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\PaginationDefaults;
use App\Http\Middleware\PiiSafeRequestLog;
use App\Http\Middleware\RequestTrace;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SentryContext;
use Illuminate\Support\Facades\Route;

// Global pipeline
Route::middleware([RequestTrace::class, SecurityHeaders::class, PiiSafeRequestLog::class, AdvancedCors::class, PaginationDefaults::class, SentryContext::class, AdvancedRateLimiter::class])->group(function () {

    // OpenAPI spec
    Route::get('/health/openapi', [OpenApiController::class, 'json']);

    // Preflight
    Route::options('/{any}', fn () => response()->noContent())->where('any', '.*');

    // Health
    Route::get('/health', [HealthController::class, 'ping']);
    Route::get('/health/live', [HealthController::class, 'live']);
    Route::get('/health/ready', [HealthController::class, 'ready']);

    // Metrics
    Route::get('/metrics', [MetricsController::class, 'scrape']);

    // Progress (% completion estimate)
    Route::get('/health/progress', [ProgressController::class, 'show']);

    // RBAC
    Route::prefix('rbac')->group(function () {
        Route::get('/roles', [RBACController::class, 'roles']);
        Route::post('/users/{userId}/roles/{roleId}', [RBACController::class, 'attachRole'])
            ->middleware(RequireRole::class.':admin');
    });

    // Social
    Route::prefix('social')->group(function () {
        Route::get('/posts', [PostController::class, 'index']);
        Route::post('/posts', [PostController::class, 'store'])->middleware(RequireRole::class.':user');

        Route::get('/messages', [MessageController::class, 'index']);
        Route::post('/messages', [MessageController::class, 'store'])->middleware(RequireRole::class.':user');
        Route::delete('/messages/{id}', [MessageController::class, 'destroy'])->middleware(RequireRole::class.':user');

        Route::get('/safe-rooms', [SafeRoomController::class, 'index']);
        Route::post('/safe-rooms', [SafeRoomController::class, 'store'])->middleware(RequireRole::class.':user');
        Route::patch('/safe-rooms/{id}/panic', [SafeRoomController::class, 'setPanicCode'])->middleware(RequireRole::class.':user');
    });

    // Marketplace
    Route::prefix('market')->group(function () {
        Route::get('/shops', [ShopController::class, 'index']);
        Route::post('/shops', [ShopController::class, 'store'])->middleware(RequireRole::class.':seller');

        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store'])->middleware(RequireRole::class.':seller');
        Route::patch('/products/{id}', [ProductController::class, 'update'])->middleware(RequireRole::class.':seller');
        Route::delete('/products/{id}', [ProductController::class, 'destroy'])->middleware(RequireRole::class.':seller');

        Route::post('/inventory/{productId}/adjust', [InventoryController::class, 'adjust'])->middleware(RequireRole::class.':seller');

        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store'])->middleware(RequireRole::class.':user');
        Route::post('/orders/{orderId}/items', [OrderController::class, 'addItem'])->middleware(RequireRole::class.':user');
        Route::patch('/orders/{orderId}/status', [OrderController::class, 'setStatus'])->middleware(RequireRole::class.':user,admin');
    });

    // Identity / Wallet / NFT
    Route::prefix('identity')->group(function () {
        Route::get('/did-profiles', [DidProfileController::class, 'index']);
        Route::post('/did-profiles', [DidProfileController::class, 'store'])->middleware(RequireRole::class.':user');
        Route::patch('/did-profiles/{id}', [DidProfileController::class, 'update'])->middleware(RequireRole::class.':user');

        Route::get('/wallets', [WalletController::class, 'index']);
        Route::post('/wallets', [WalletController::class, 'store'])->middleware(RequireRole::class.':user');
        Route::delete('/wallets/{id}', [WalletController::class, 'destroy'])->middleware(RequireRole::class.':user');

        Route::get('/nft/transfers', [NftTransferController::class, 'index']);
        Route::post('/nft/transfers', [NftTransferController::class, 'store'])->middleware(RequireRole::class.':user');
    });

    // Feature-gated
    Route::middleware([EnsureFeatureEnabled::class.':DAO'])->prefix('dao')->group(function () {
        Route::get('/proposals', fn () => \App\Support\ApiResponse::success(
            \Illuminate\Support\Facades\DB::table('proposals')->orderByDesc('id')->limit(50)->get()
        ));
    });
    Route::middleware([EnsureFeatureEnabled::class.':EXCHANGE'])->prefix('exchange')->group(function () {
        Route::get('/health', fn () => \App\Support\ApiResponse::success(['ok' => true]));
    });
});
