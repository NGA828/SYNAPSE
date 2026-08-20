<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
