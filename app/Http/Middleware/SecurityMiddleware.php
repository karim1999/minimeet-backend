<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Security\AuditLogService;
use App\Services\Security\RateLimitingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SecurityMiddleware
{
    public function __construct(
        private readonly RateLimitingService $rateLimiter,
        private readonly AuditLogService $auditLog
    ) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // Skip security middleware in testing environment to avoid interference
        if (app()->environment('testing')) {
            return $next($request);
        }
        // Check for suspicious activity first
        $suspiciousCheck = $this->rateLimiter->checkSuspiciousActivity($request);
        if ($suspiciousCheck['suspicious']) {
            $this->handleSuspiciousActivity($request, $suspiciousCheck);

            if ($suspiciousCheck['action'] === 'block') {
                return response()->json([
                    'success' => false,
                    'message' => 'Request blocked due to suspicious activity.',
                ], 403);
            }
        }

        // Apply API rate limiting
        $rateLimitResult = $this->rateLimiter->checkApiRateLimit($request);
        if (! $rateLimitResult['allowed']) {
            return $this->rateLimitResponse($rateLimitResult);
        }

        // Apply progressive rate limiting for authenticated users
        if ($request->user()) {
            $progressiveResult = $this->rateLimiter->applyProgressiveRateLimit($request, (string) $request->user()->getKey());
            if (! $progressiveResult['allowed']) {
                return $this->progressiveRateLimitResponse($progressiveResult);
            }
        }

        // Process the request
        $response = $next($request);

        // Add security headers
        $this->addSecurityHeaders($response);

        // Add rate limit headers
        $this->addRateLimitHeaders($response, $rateLimitResult);

        // Log successful requests for monitoring
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->logSuccessfulRequest($request);
        } else {
            $this->logFailedRequest($request, $response);
        }

        return $response;
    }

    /**
     * Handle suspicious activity detection.
     */
    private function handleSuspiciousActivity(Request $request, array $suspiciousCheck): void
    {
        $this->auditLog->logSuspiciousActivity(
            $request->user(),
            $suspiciousCheck['reason'],
            'Suspicious activity pattern detected',
            [
                'action' => $suspiciousCheck['action'],
                'url' => $request->url(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );
    }

    /**
     * Generate rate limit exceeded response.
     */
    private function rateLimitResponse(array $rateLimitResult): Response
    {
        return response()->json([
            'success' => false,
            'message' => 'Rate limit exceeded. Please try again later.',
            'retry_after' => $rateLimitResult['retry_after'],
        ], 429)
            ->header('Retry-After', $rateLimitResult['retry_after'])
            ->header('X-RateLimit-Limit', $rateLimitResult['limit'] ?? 'N/A')
            ->header('X-RateLimit-Remaining', 0);
    }

    /**
     * Generate progressive rate limit response.
     */
    private function progressiveRateLimitResponse(array $progressiveResult): Response
    {
        return response()->json([
            'success' => false,
            'message' => $progressiveResult['message'],
            'progressive_level' => $progressiveResult['progressive_level'],
            'retry_after' => $progressiveResult['retry_after'],
        ], 429)
            ->header('Retry-After', $progressiveResult['retry_after'])
            ->header('X-Progressive-Level', $progressiveResult['progressive_level']);
    }

    /**
     * Add security headers to the response.
     */
    private function addSecurityHeaders(SymfonyResponse $response): void
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'",
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ];

        foreach ($headers as $header => $value) {
            $response->headers->set($header, $value, false);
        }
    }

    /**
     * Add rate limiting headers to the response.
     */
    private function addRateLimitHeaders(SymfonyResponse $response, array $rateLimitResult): void
    {
        if (isset($rateLimitResult['limit'])) {
            $response->headers->set('X-RateLimit-Limit', (string) $rateLimitResult['limit'], false);
        }

        if (isset($rateLimitResult['remaining'])) {
            $response->headers->set('X-RateLimit-Remaining', (string) $rateLimitResult['remaining'], false);
        }

        if (isset($rateLimitResult['reset_at'])) {
            $response->headers->set('X-RateLimit-Reset', (string) $rateLimitResult['reset_at'], false);
        }
    }

    /**
     * Log successful requests for monitoring.
     */
    private function logSuccessfulRequest(Request $request): void
    {
        // Only log sensitive or important endpoints to avoid log spam
        $importantEndpoints = [
            '/auth/',
            '/admin/',
            '/users/',
            '/export/',
            '/delete/',
        ];

        $shouldLog = false;
        foreach ($importantEndpoints as $endpoint) {
            if (str_contains($request->path(), $endpoint)) {
                $shouldLog = true;
                break;
            }
        }

        if ($shouldLog) {
            $this->auditLog->logSecurityEvent(
                $request->user(),
                'api_request',
                'API request processed successfully',
                [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'status_code' => 200,
                ],
                'info',
                $request->ip(),
                $request->userAgent()
            );
        }
    }

    /**
     * Log failed requests.
     */
    private function logFailedRequest(Request $request, SymfonyResponse $response): void
    {
        $statusCode = $response->getStatusCode();

        // Log client errors (4xx) and server errors (5xx)
        if ($statusCode >= 400) {
            $severity = $statusCode >= 500 ? 'error' : 'warning';

            $this->auditLog->logSecurityEvent(
                $request->user(),
                'api_error',
                "API request failed with status {$statusCode}",
                [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'status_code' => $statusCode,
                    'query_params' => $request->query->all(),
                ],
                $severity,
                $request->ip(),
                $request->userAgent()
            );
        }
    }
}
