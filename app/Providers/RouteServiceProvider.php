<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
  

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
public function boot(): void
{
    $this->configureRateLimiting();

    $this->routes(function () {
        Route::middleware('api')
            ->prefix('api')
            ->group(base_path('routes/api.php'));

        Route::middleware('web')
            ->group(base_path('routes/web.php'));

        // Add this if you have a v1.php route file
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(base_path('routes/api/v1.php'));
    });
}

protected function configureRateLimiting(): void
{
    // Global API rate limiter
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });

    // Registration-specific rate limiter
    RateLimiter::for('registration', function (Request $request) {
        return Limit::perMinute(5)->by($request->ip());
    });
}
}
