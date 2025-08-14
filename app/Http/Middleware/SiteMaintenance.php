<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Setting;

class SiteMaintenance
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && auth()->user()->is_admin) {
            return $next($request);
        }

        $enabled = (bool) Setting::get('site_maintenance', false);
        if ($enabled && !str_starts_with($request->path(), 'admin')) {
            return response()->view('errors.503', [], 503);
        }
        return $next($request);
    }
}

