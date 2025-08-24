<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiting for the application.
     */
    private function configureRateLimiting(): void
    {
        // Central authentication rate limiting
        RateLimiter::for('central-auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip())->response(function (Request $request, array $headers) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many authentication attempts. Please try again later.',
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'error_code' => 'AUTH_RATE_LIMIT',
                    ],
                ], 429, $headers);
            });
        });

        // Global authentication rate limiting
        RateLimiter::for('central-auth-global', function (Request $request) {
            return Limit::perMinute(1000)->response(function (Request $request, array $headers) {
                return response()->json([
                    'success' => false,
                    'message' => 'Service temporarily unavailable due to high demand. Please try again later.',
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'error_code' => 'AUTH_GLOBAL_LIMIT',
                    ],
                ], 503, $headers);
            });
        });

        // API rate limiting for authenticated requests
        RateLimiter::for('central-api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(100)->by($request->user()->id)
                : Limit::perMinute(20)->by($request->ip());
        });
    }
}
