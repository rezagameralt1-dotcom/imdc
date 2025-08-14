<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Middleware\SetLocale;

// تغییر زبان (بدون CSRF): /locale/fa یا /locale/en
Route::get('/locale/{l}', function (string $l) {
    $locale = in_array($l, ['fa','en'], true) ? $l : 'en';
    session(['locale' => $locale]);
    return back();
})->name('locale.change');

// مسیر دیباگ
Route::get('/debug/locale', function () {
    return response()->json([
        'session_locale' => session('locale'),
        'app_locale'     => app()->getLocale(),
        'fallback'       => config('app.fallback_locale'),
    ]);
});

// صفحات با میدلور SetLocale
Route::middleware(SetLocale::class)->group(function () {
    Route::get('/', fn() => Inertia::render('Dashboard'));
    Route::get('/settings', fn() => Inertia::render('Settings'));
});
