<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\Did\DidMeController;
use App\Inventory\Http\Controllers\InventoryController;
use App\Nfts\Http\Controllers\NftController;
use App\Orders\Http\Controllers\OrderController;
use App\Products\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Health check endpoint (no auth required for monitoring)
    // Note: HealthController was removed, health endpoint disabled
    
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::prefix('market')->group(function () {
            Route::get('products', [ProductController::class, 'index']);
            Route::post('products', [ProductController::class, 'store']);
        });

        // User info endpoint
        Route::get('me', \App\Http\Controllers\Api\MeController::class);
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
                ->middleware('role:Admin|Auditor');
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

        // NFT routes (only registered when FEATURE_NFT=true)
        if (config('nft.enabled', false)) {
            Route::post('nfts/mint', [NftController::class, 'mint']);
            Route::post('nfts/transfer', [NftController::class, 'transfer']);
            Route::get('nfts', [NftController::class, 'index']);
            Route::get('nfts/{id}', [NftController::class, 'show']);
        }

        // DID routes (always registered; feature flag checked in controller)
        Route::prefix('did')->group(function () {
            Route::get('me', [DidMeController::class, 'show']);
            Route::post('me', [DidMeController::class, 'store']);
            Route::put('me', [DidMeController::class, 'update']);
        });

        // Linking routes (always registered; feature flag checked by middleware)
        Route::middleware(['feature:linking'])->prefix('linking')->group(function () {
            Route::post('did-order', [\App\Http\Controllers\Api\Linking\DidOrderLinkController::class, 'store'])
                ->middleware('role:Admin');
            Route::post('did-nft', [\App\Http\Controllers\Api\Linking\DidNftLinkController::class, 'store'])
                ->middleware('role:Admin');
            Route::post('order-nft', [\App\Http\Controllers\Api\Linking\OrderNftLinkController::class, 'store'])
                ->middleware('role:Admin');
            Route::get('did/{didId}', [\App\Http\Controllers\Api\Linking\LinkingQueryController::class, 'getByDid'])
                ->middleware('role:Admin|Auditor');
            Route::get('order/{orderId}', [\App\Http\Controllers\Api\Linking\LinkingQueryController::class, 'getByOrder'])
                ->middleware('role:Admin|Auditor');
            Route::get('nft/{nftId}', [\App\Http\Controllers\Api\Linking\LinkingQueryController::class, 'getByNft'])
                ->middleware('role:Admin|Auditor');
        });

        // DAO routes (always registered; feature flag checked by middleware)
        Route::middleware(['feature:dao'])->prefix('dao')->group(function () {
            Route::post('proposals', [\App\Http\Controllers\Api\Dao\DaoProposalController::class, 'store'])
                ->middleware('role:Admin');
            Route::get('proposals', [\App\Http\Controllers\Api\Dao\DaoProposalController::class, 'index'])
                ->middleware('role:Admin|Auditor');
            Route::get('proposals/{id}', [\App\Http\Controllers\Api\Dao\DaoProposalController::class, 'show'])
                ->middleware('role:Admin|Auditor');
            Route::post('proposals/{id}/vote', [\App\Http\Controllers\Api\Dao\DaoVoteController::class, 'store'])
                ->middleware('role:Admin');
        });

        // Pharma routes (always registered; feature flag checked by middleware)
        Route::middleware(['feature:pharma'])->prefix('pharma')->group(function () {
            Route::get('drugs', [\App\Http\Controllers\Api\Pharma\PharmaDrugController::class, 'index'])
                ->middleware('role:Admin|Auditor');
            Route::get('drugs/{id}', [\App\Http\Controllers\Api\Pharma\PharmaDrugController::class, 'show'])
                ->middleware('role:Admin|Auditor');
            Route::post('check', [\App\Http\Controllers\Api\Pharma\PharmaCheckController::class, 'check'])
                ->middleware('role:Admin');
        });

        // Places routes (always registered; feature flag checked by middleware)
        Route::middleware(['feature:vr'])->prefix('places')->group(function () {
            Route::get('', [\App\Http\Controllers\Api\Place\PlaceController::class, 'index'])
                ->middleware('role:Admin|Auditor');
            Route::get('{id}', [\App\Http\Controllers\Api\Place\PlaceController::class, 'show'])
                ->middleware('role:Admin|Auditor');
            Route::post('', [\App\Http\Controllers\Api\Place\PlaceController::class, 'store'])
                ->middleware('role:Admin');
            Route::post('{id}/link-nft', [\App\Http\Controllers\Api\Place\PlaceController::class, 'linkNft'])
                ->middleware('role:Admin');
        });

        // Training routes (always registered; feature flag checked by middleware)
        Route::middleware(['feature:training'])->prefix('training')->group(function () {
            // Public/authenticated: List and view courses
            Route::get('courses', [\App\Http\Controllers\Api\Training\CourseController::class, 'index'])
                ->middleware('role:Admin|Auditor|Teacher|User');
            Route::get('courses/{id}', [\App\Http\Controllers\Api\Training\CourseController::class, 'show'])
                ->middleware('role:Admin|Auditor|Teacher|User');

            // Authenticated user: Enroll
            Route::post('courses/{id}/enroll', [\App\Http\Controllers\Api\Training\CourseController::class, 'enroll'])
                ->middleware('role:Admin|Teacher|User');

            // User endpoints: View own data
            Route::get('users/me/enrollments', [\App\Http\Controllers\Api\Training\UserTrainingController::class, 'enrollments'])
                ->middleware('role:Admin|Teacher|User');
            Route::get('users/me/skill-nfts', [\App\Http\Controllers\Api\Training\UserTrainingController::class, 'skillNfts'])
                ->middleware('role:Admin|Teacher|User');

            // Teacher/Admin: Create, update, publish courses
            Route::post('courses', [\App\Http\Controllers\Api\Training\CourseController::class, 'store'])
                ->middleware('role:Admin|Teacher');
            Route::put('courses/{id}', [\App\Http\Controllers\Api\Training\CourseController::class, 'update'])
                ->middleware('role:Admin|Teacher');
            Route::post('courses/{id}/publish', [\App\Http\Controllers\Api\Training\CourseController::class, 'publish'])
                ->middleware('role:Admin|Teacher');

            // Teacher/Admin: Complete enrollments (issue skill NFT)
            Route::post('enrollments/{id}/complete', [\App\Http\Controllers\Api\Training\EnrollmentController::class, 'complete'])
                ->middleware('role:Admin|Teacher');
        });

        // Admin Panel routes (always registered; feature flag checked by middleware)
        Route::middleware(['feature:reports'])->prefix('admin')->group(function () {
            // Reports endpoints
            Route::get('reports/system-overview', [\App\Http\Controllers\Api\Admin\ReportsController::class, 'systemOverview'])
                ->middleware('role:Admin');
            Route::get('reports/guardrail-runs', [\App\Http\Controllers\Api\Admin\ReportsController::class, 'guardrailRuns'])
                ->middleware('role:Admin');
            Route::get('reports/audit-logs', [\App\Http\Controllers\Api\Admin\ReportsController::class, 'auditLogs'])
                ->middleware('role:Admin');

            // Admin health check
            Route::get('health', [\App\Http\Controllers\Api\Admin\HealthController::class, 'index'])
                ->middleware('role:Admin');
        });

        // Admin Users Management routes (gated by FEATURE_ADMIN)
        Route::middleware(['feature:admin'])->prefix('admin')->group(function () {
            Route::get('users', [\App\Http\Controllers\Api\Admin\UsersController::class, 'index'])
                ->middleware('role:Admin');
            Route::get('users/{id}', [\App\Http\Controllers\Api\Admin\UsersController::class, 'show'])
                ->middleware('role:Admin');
            Route::put('users/{id}/roles', [\App\Http\Controllers\Api\Admin\UsersController::class, 'assignRoles'])
                ->middleware('role:Admin');
        });
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
    Route::get('/me', \App\Http\Controllers\Api\MeController::class);
    // Admin ping: requires Admin role only (LOCKED - stable baseline)
    if (class_exists(\App\Http\Controllers\Api\AdminPingController::class)) {
        Route::get('/admin/ping', \App\Http\Controllers\Api\AdminPingController::class)
            ->middleware('role:Admin');
    }
    // Access policy: Admin OR Auditor (locked).
    if (class_exists(\App\Http\Controllers\Api\AuditPingController::class)) {
        Route::get('/audit/ping', \App\Http\Controllers\Api\AuditPingController::class)
            ->middleware('role:Admin|Auditor');
    }
});




