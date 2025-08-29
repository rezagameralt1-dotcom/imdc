<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ForceApiJson;

class IMDCApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // تضمین می‌کند ForceApiJson قبل از auth:sanctum اجرا شود
        Route::prependMiddlewareToGroup('api', ForceApiJson::class);
    }
}
