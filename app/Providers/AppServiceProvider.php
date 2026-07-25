<?php

namespace App\Providers;

use App\Http\Controllers\GatewayController;
use App\Http\Routing\GatewayDefinition;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        $this->configureRateLimiting();

        $this->registerHealthChecks();

        $this->registerGatewayMacro();
    }

    /**
     * Configure rate limiting for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()->id ?? $request->ip());
        });
    }

    /**
     * Register the application's health checks.
     */
    protected function registerHealthChecks(): void
    {
        Health::checks([
            DatabaseCheck::new(),
            CacheCheck::new(),
            UsedDiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage(70)
                ->failWhenUsedSpaceIsAbovePercentage(90),
        ]);
    }

    /**
     * Register the Route::gateway() macro for proxying requests to an upstream URL.
     *
     * Example:
     *   Route::gateway('/v1/{path}', function ($route) {
     *       $route->to('https://api.openai.com');
     *   });
     */
    protected function registerGatewayMacro(): void
    {
        Route::macro('gateway', function (string $uri, Closure $callback) {
            $definition = new GatewayDefinition;
            $callback($definition);

            return Route::any($uri, function (Request $request) use ($definition) {
                return app(GatewayController::class)($request, $definition);
            })->where('path', '.*');
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
