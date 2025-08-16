<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class AppLocaleMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // اولویت: هدر → query → سشن → preferred language → پیش‌فرض
        $locale = $request->header('X-Locale')
            ?: $request->query('locale')
            ?: session('locale')
            ?: $request->getPreferredLanguage(['fa', 'en'])
            ?: config('app.locale', 'en');

        App::setLocale($locale);

        return $next($request);
    }
}
