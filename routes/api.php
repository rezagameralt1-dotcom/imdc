<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthTokenController;

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
