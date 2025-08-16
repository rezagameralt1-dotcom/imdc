<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds lightweight request context to Sentry if available.
 */
class SentryContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->bound('sentry')) {
            /** @var \Sentry\State\HubInterface $sentry */
            $sentry = app('sentry');
            $tid = $request->headers->get('X-Trace-Id');
            $uid = $request->headers->get('X-User-Id') ?: $request->query('user_id');

            $sentry->configureScope(function (\Sentry\State\Scope $scope) use ($request, $tid, $uid) {
                if ($tid) {
                    $scope->setTag('trace_id', $tid);
                }
                if ($uid) {
                    $scope->setUser(['id' => (string) $uid]);
                }
                $scope->setTag('method', $request->getMethod());
                $scope->setTag('path', $request->path());
                $scope->setExtra('ip', $request->ip());
                $scope->setExtra('agent', substr((string) $request->userAgent(), 0, 180));
            });
        }

        return $next($request);
    }
}
