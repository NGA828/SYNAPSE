<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The `api` group already applies the `throttle:api` rate limiter
        // (60 requests/minute by default). Prepend Sanctum's stateful
        // middleware so first-party SPA requests are recognised.
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRoleIs::class,
            'tenant' => \App\Http\Middleware\IdentifyTenant::class,
            'subscription' => \App\Http\Middleware\EnforceSubscription::class,
            'feature' => \App\Http\Middleware\EnsureFeature::class,
            'teaching.assignment' => \App\Http\Middleware\CheckTeachingAssignment::class,
            'class.access' => \App\Http\Middleware\EnsureClassAccess::class,
            'password.rotated' => \App\Http\Middleware\EnsurePasswordIsRotated::class,
        ]);

        // Credential endpoints get their own, much tighter limits.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by($request->input('email').'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('password', fn (Request $request) => [
            Limit::perMinutes(15, 5)->by($request->input('email').'|'.$request->ip()),
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Queued notifications must never take the whole job down because a
        // single provider is unreachable; they are retried with back-off.
        $exceptions->dontReport([
            \Illuminate\Http\Client\ConnectionException::class,
        ]);
    })->create();
