<?php
use App\Http\Controllers\AuthTokenController;
use App\Http\Controllers\DemoController;




Route::get('/ping', fn() => response()->json(['success'=>true,'data'=>'pong']));

// صدور توکن
Route::post('/auth/token', [AuthTokenController::class, 'issue']);

// مسیر امن پایه
Route::middleware(['auth:sanctum'])->get('/secure/ping', function () {
    return response()->json(['success'=>true,'data'=>'pong']);
});

// --- RBAC protected routes ---
Route::middleware(['auth:sanctum','role:Admin'])->get('/admin/ping', function () {
    return response()->json(['success'=>true,'data'=>'admin-pong']);
});

Route::middleware(['auth:sanctum','permission:system.view'])->get('/perm/ping', function () {
    return response()->json(['success'=>true,'data'=>'perm-pong']);
});

// ===== M03: Marketplace Base (protected) =====

Route::middleware(['auth:sanctum'])->group(function () {
    // Categories (نیاز به system.view برای مشاهده، inventory.manage برای مدیریت)
    Route::get('/market/categories', [CategoryController::class, 'index'])->middleware('permission:system.view');
    Route::get('/market/categories/{id}', [CategoryController::class, 'show'])->middleware('permission:system.view');
    Route::post('/market/categories', [CategoryController::class, 'store'])->middleware('permission:inventory.manage');
    Route::put('/market/categories/{id}', [CategoryController::class, 'update'])->middleware('permission:inventory.manage');
    Route::delete('/market/categories/{id}', [CategoryController::class, 'destroy'])->middleware('permission:inventory.manage');

    // Products
    Route::get('/market/products', [ProductController::class, 'index'])->middleware('permission:system.view');
    Route::get('/market/products/{id}', [ProductController::class, 'show'])->middleware('permission:system.view');
    Route::post('/market/products', [ProductController::class, 'store'])->middleware('permission:inventory.manage');
    Route::put('/market/products/{id}', [ProductController::class, 'update'])->middleware('permission:inventory.manage');
    Route::delete('/market/products/{id}', [ProductController::class, 'destroy'])->middleware('permission:inventory.manage');

    // Inventory
    Route::get('/market/products/{productId}/inventory', [InventoryController::class, 'show'])->middleware('permission:inventory.read|inventory.manage');
    Route::post('/market/products/{productId}/inventory/add', [InventoryController::class, 'add'])->middleware('permission:inventory.manage');
    Route::post('/market/products/{productId}/inventory/adjust', [InventoryController::class, 'adjust'])->middleware('permission:inventory.manage');
    Route::get('/market/products/{productId}/inventory/movements', [InventoryController::class, 'movements'])->middleware('permission:inventory.read|inventory.manage');

    // Orders
    Route::get('/market/orders', [OrderController::class, 'index'])->middleware('permission:orders.read');
    Route::get('/market/orders/{id}', [OrderController::class, 'show'])->middleware('permission:orders.read');
    Route::post('/market/orders', [OrderController::class, 'store'])->middleware('permission:orders.write');
    Route::post('/market/orders/{id}/pay', [OrderController::class, 'pay'])->middleware('permission:orders.write');
    Route::post('/market/orders/{id}/cancel', [OrderController::class, 'cancel'])->middleware('permission:orders.write');
});

Route::get("demo/hello", [DemoController::class, "hello"]);
Route::middleware("auth:sanctum")->get("demo/secure", [DemoController::class, "secure"]);
