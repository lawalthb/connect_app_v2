<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\SwipeRateLimit;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(base_path('routes/api/v1.php'));

            Route::middleware('api')
                ->prefix('api/v2')
                ->group(base_path('routes/api/v2.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register custom middleware aliases
        $middleware->alias([
            'swipe.limit' => SwipeRateLimit::class,
        ]);

        // API middleware group configuration
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withCommands([
        \App\Console\Commands\ResetDailySwipes::class,
    ])
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        // Reset daily swipes at midnight
        $schedule->command('swipes:reset-daily')
                 ->dailyAt('00:01')
                 ->timezone('UTC');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
