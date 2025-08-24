<?php

namespace App\Http\Middleware\Central;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ThrottleAuthAttempts
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // Check per-IP rate limiting
        $ipKey = 'auth:'.$request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 5)) {
            $seconds = RateLimiter::availableIn($ipKey);

            return response()->json([
                'success' => false,
                'message' => 'Too many authentication attempts. Please try again later.',
                'retry_after' => $seconds,
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'error_code' => 'AUTH_RATE_LIMIT',
                ],
            ], Response::HTTP_TOO_MANY_REQUESTS)->header('Retry-After', $seconds);
        }

        // Check global rate limiting
        $globalKey = 'auth:global';
        if (RateLimiter::tooManyAttempts($globalKey, 1000)) {
            return response()->json([
                'success' => false,
                'message' => 'Service temporarily unavailable due to high demand. Please try again later.',
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'error_code' => 'AUTH_GLOBAL_LIMIT',
                ],
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $response = $next($request);

        // Only increment rate limiting on failed attempts
        if ($this->shouldIncrementRateLimit($request, $response)) {
            RateLimiter::hit($ipKey, 60); // 1 minute decay
            RateLimiter::hit($globalKey, 60);
        }

        return $response;
    }

    /**
     * Determine if the rate limit should be incremented based on the response.
     */
    private function shouldIncrementRateLimit(Request $request, SymfonyResponse $response): bool
    {
        // Only increment on failed login attempts (422 validation error)
        return $request->isMethod('POST')
            && $request->is('*/login')
            && $response->getStatusCode() === Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
