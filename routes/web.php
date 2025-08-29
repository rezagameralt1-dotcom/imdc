<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return response()->json([
        'success' => false,
        'error'   => 'Unauthenticated',
    ], 401);
})->name('login');
