<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class ApplyLocaleFromSession
{
    public function handle($request, Closure $next)
    {
        $locale = Session::get('app_locale', config('app.locale'));
        App::setLocale($locale);

        return $next($request);
    }
}
