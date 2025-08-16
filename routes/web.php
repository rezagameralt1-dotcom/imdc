<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

# ---------- Home ----------
if (class_exists(\App\Http\Controllers\Site\HomeController::class)) {
    Route::get('/', [\App\Http\Controllers\Site\HomeController::class, 'index'])->name('home');
} else {
    Route::get('/', fn () => view('welcome'))->name('home');
}

# ---------- UI demo ----------
Route::get('/ui', fn () => view('ui.index'))->name('ui');

# ---------- Blog ----------
if (class_exists(\App\Http\Controllers\Site\BlogController::class)) {
    Route::get('/blog', [\App\Http\Controllers\Site\BlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/{slug}', [\App\Http\Controllers\Site\BlogController::class, 'show'])->name('blog.show');
}

# ---------- Static pages ----------
if (class_exists(\App\Http\Controllers\Site\PageController::class)) {
    Route::get('/pages/{slug}', [\App\Http\Controllers\Site\PageController::class, 'show'])->name('pages.show');
}

# ---------- Contact ----------
if (class_exists(\App\Http\Controllers\Site\ContactController::class)) {
    Route::get('/contact', [\App\Http\Controllers\Site\ContactController::class, 'form'])->name('contact.form');
    Route::post('/contact', [\App\Http\Controllers\Site\ContactController::class, 'submit'])->name('contact.submit');
}

# ---------- Admin dashboard (auth) ----------
if (class_exists(\App\Http\Controllers\Admin\DashboardController::class)) {
    Route::middleware('auth')->group(function () {
        Route::get('/admin', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])
            ->name('admin.dashboard');
    });
}

# ---------- ساده‌های کمکی ----------
Route::get('/login', fn () => response('LOGIN OK', 200))->name('login');

# روت تست سلامت بدون هیچ میدلور یا وابستگی
Route::middleware([])->get('/healthz', function () {
    return response('HEALTH OK', 200);
})->name('healthz');

# ---------- SPA Auth (Session JSON) ----------
$csrfClass = class_exists(\App\Http\Middleware\VerifyCsrfToken::class)
    ? \App\Http\Middleware\VerifyCsrfToken::class
    : (class_exists(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ? \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class
        : null);

Route::prefix('spa-auth')->group(function () use ($csrfClass) {

    // Login
    $login = Route::post('/login', function (Request $request) {
        $creds = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($creds)) {
            return response()->json(['ok' => false, 'message' => 'Invalid credentials'], 422);
        }

        $request->session()->regenerate();
        $user = $request->user();

        return response()->json([
            'ok' => true,
            'user' => [
                'id'       => $user->id,
                'name'     => $user->name,
                'email'    => $user->email,
                'is_admin' => (bool)($user->is_admin ?? false),
            ],
        ]);
    })->name('spa.login');
    if ($csrfClass) { $login->withoutMiddleware([$csrfClass]); }

    // Me
    Route::get('/me', function (Request $request) {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false, 'user' => null], 200);
        }
        return response()->json([
            'ok' => true,
            'user' => [
                'id'       => $user->id,
                'name'     => $user->name,
                'email'    => $user->email,
                'is_admin' => (bool)($user->is_admin ?? false),
            ],
        ]);
    })->name('spa.me');

    // Logout
    $logout = Route::post('/logout', function (Request $request) {
        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    })->name('spa.logout');
    if ($csrfClass) { $logout->withoutMiddleware([$csrfClass]); }
});

Route::get('/api/health', fn() => response()->json(['ok'=>true]))->name('api.health');
