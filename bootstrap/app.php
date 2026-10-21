<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // keep legacy alias used by the auth controllers (guest-only pages)
        $middleware->alias([
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API responses stay JSON like in the old app
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->is('api/*') || $request->expectsJson()
        );

        // contract for the FE quota snackbar (billing, D18)
        $exceptions->render(function (\App\Exceptions\QuotaExceededException $e, $request) {
            return response()->json([
                'error' => 'quota_exceeded',
                'message' => $e->getMessage(),
                'remaining' => $e->remaining,
                'requested' => $e->requested,
            ], 422);
        });
    })->create();
