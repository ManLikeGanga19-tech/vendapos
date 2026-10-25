<?php

use App\Http\Api\V1\Middleware\EnsureDeviceBinding;
use App\Http\Api\V1\Middleware\LimitRequestBody;
use App\Http\Api\V1\Middleware\RefuseSuspendedTenant;
use App\Http\Api\V1\Middleware\ThrottleStrictly;
use App\Http\Middleware\TrustCloudflare;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')->group(base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'device.bound' => EnsureDeviceBinding::class,
            'body.limit' => LimitRequestBody::class,
            'throttle.strict' => ThrottleStrictly::class,
            'tenant.active' => RefuseSuspendedTenant::class,
            'ability' => CheckForAnyAbility::class,
        ]);

        // Client IP from Cloudflare only (see TrustCloudflare), never from a caller's own headers.
        $middleware->replace(TrustProxies::class, TrustCloudflare::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'webhooks/*') || $request->expectsJson(),
        );
    })->create();
