<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ErrorReportingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind Sentry client if package is installed and DSN exists
        $dsn = env('SENTRY_LARAVEL_DSN') ?: env('SENTRY_DSN');
        if ($dsn && class_exists(\Sentry\Laravel\ServiceProvider::class)) {
            // Defer to Sentry's own provider but also bind a simple accessor
            $this->app->register(\Sentry\Laravel\ServiceProvider::class);
            $this->app->alias('sentry', \Sentry\State\HubInterface::class);
        }
    }

    public function boot(): void
    {
        // no-op
    }
}
