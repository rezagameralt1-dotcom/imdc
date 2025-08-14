<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\RegisterSpaController;
use App\Http\Controllers\Auth\ForgotPasswordSpaController;
use App\Http\Controllers\Auth\ResetPasswordSpaController;

/*
|-----------------------------------------------------------------------
| Public pages & UI demos
|-----------------------------------------------------------------------
*/
Route::redirect('/', '/ui')->name('home');
Route::get('/ui', fn () => view('ui.index'))->name('ui');
Route::view('/spa', 'welcome')->name('spa.demo');
Route::get('/login', fn () => redirect()->route('spa.demo'))->name('login');

/*
|-----------------------------------------------------------------------
| SPA Auth helpers (Sanctum session-based)
|-----------------------------------------------------------------------
*/
Route::post('/login-spa', function (Request $request) {
    $credentials = $request->validate([
        'email'    => ['required', 'email'],
        'password' => ['required'],
    ]);

    if (! Auth::attempt($credentials, true)) {
        return response()->json(['message' => 'Invalid credentials'], 422);
    }

    $request->session()->regenerate();

    $user = $request->user();
    // اگر کاربر غیرفعال است، بلافاصله سشن را باطل کن و پیام بده
    if (isset($user->is_active) && !$user->is_active) {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' => 'حساب کاربری غیرفعال است'], 423);
    }

    return response()->json([
        'ok'   => true,
        'user' => $user,
    ]);
})->name('login.spa');

Route::post('/logout-spa', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return response()->json(['ok' => true]);
})->name('logout.spa');

Route::get('/me', fn (Request $request) => response()->json($request->user()))->name('me');

// Register / Forgot / Reset (Rate-limited)
Route::post('/register-spa', [RegisterSpaController::class, 'store'])
    ->middleware('throttle:6,1')->name('register.spa');

Route::post('/forgot-password', [ForgotPasswordSpaController::class, 'send'])
    ->middleware('throttle:3,1')->name('password.email');

Route::post('/reset-password', [ResetPasswordSpaController::class, 'reset'])
    ->middleware('throttle:3,1')->name('password.update');

/*
|-----------------------------------------------------------------------
| Admin area (protected)
|-----------------------------------------------------------------------
*/
Route::middleware(['auth', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/dashboard', 'admin.dashboard')->name('dashboard');
    // منبع کاربران با اقدامات اصلی (لیست/ویرایش/حذف)
    Route::resource('users', UserController::class)->except(['create','store','show']);
    Route::view('/content', 'admin.content.index')->name('content.index');
    Route::view('/assets', 'admin.assets.index')->name('assets.index');
    Route::view('/settings', 'admin.settings.index')->name('settings.index');
});

