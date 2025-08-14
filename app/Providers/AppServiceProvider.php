<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // در محیط پروداکشن، لینک‌ها را روی HTTPS مجبور کن (اگر پشت پروکسی هستید TrustProxies را درست تنظیم کنید)
        if (config('app.env') === 'production') {
            try { URL::forceScheme('https'); } catch (\Throwable $e) { /* ignore */ }
        }
    }
}

