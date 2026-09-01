<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\NoCache;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        App\Providers\RateLimitServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(NoCache::class);

        $middleware->alias([
            'auth.session' => Authenticate::class,
            'verified' => EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(
                    ['message' => 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.', 'error' => 'rate_limited'],
                    Response::HTTP_TOO_MANY_REQUESTS,
                    [
                        'Retry-After' => $e->getHeaders()['Retry-After'] ?? 0,
                        'X-RateLimit-Limit' => $e->getHeaders()['X-RateLimit-Limit'] ?? 0,
                        'X-RateLimit-Remaining' => $e->getHeaders()['X-RateLimit-Remaining'] ?? 0,
                    ],
                );
            }

            return null;
        });
    })->create();
