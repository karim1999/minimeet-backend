<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RateLimitingService
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly AuditLogService $auditLog
    ) {}

    /**
     * Check and apply rate limiting for authentication attempts.
     */
    public function checkAuthRateLimit(Request $request, string $email): array
    {
        $key = $this->getAuthRateLimitKey($request, $email);

        // Check IP-based rate limiting (more restrictive)
        $ipKey = 'auth_ip:'.$request->ip();
        if ($this->limiter->tooManyAttempts($ipKey, 10)) { // 10 attempts per hour per IP
            $this->logRateLimitViolation($request, 'auth_ip_limit', $email);

            return [
                'allowed' => false,
                'type' => 'ip_limit',
                'retry_after' => $this->limiter->availableIn($ipKey),
                'message' => 'Too many authentication attempts from this IP address.',
            ];
        }

        // Check email-based rate limiting
        if ($this->limiter->tooManyAttempts($key, 5)) { // 5 attempts per hour per email
            $this->logRateLimitViolation($request, 'auth_email_limit', $email);

            return [
                'allowed' => false,
                'type' => 'email_limit',
                'retry_after' => $this->limiter->availableIn($key),
                'message' => 'Too many authentication attempts for this email address.',
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Record a failed authentication attempt.
     */
    public function recordFailedAuth(Request $request, string $email): void
    {
        $key = $this->getAuthRateLimitKey($request, $email);
        $ipKey = 'auth_ip:'.$request->ip();

        $this->limiter->hit($key, 3600); // 1 hour window
        $this->limiter->hit($ipKey, 3600); // 1 hour window

        // Log the failed attempt
        Log::warning('Failed authentication attempt', [
            'email' => $email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'attempts_for_email' => $this->limiter->attempts($key),
            'attempts_for_ip' => $this->limiter->attempts($ipKey),
        ]);
    }

    /**
     * Clear rate limiting after successful authentication.
     */
    public function clearAuthRateLimit(Request $request, string $email): void
    {
        $key = $this->getAuthRateLimitKey($request, $email);
        $this->limiter->clear($key);
    }

    /**
     * Check rate limiting for API endpoints.
     */
    public function checkApiRateLimit(Request $request, ?string $identifier = null): array
    {
        $identifier = $identifier ?: $this->getApiIdentifier($request);
        $key = 'api:'.$identifier;

        // Different limits based on authentication status
        $maxAttempts = $request->user() ? 1000 : 100; // Higher limit for authenticated users
        $decayMinutes = 60;

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $this->logRateLimitViolation($request, 'api_limit', $identifier);

            return [
                'allowed' => false,
                'retry_after' => $this->limiter->availableIn($key),
                'remaining' => 0,
                'limit' => $maxAttempts,
            ];
        }

        $this->limiter->hit($key, $decayMinutes * 60);

        return [
            'allowed' => true,
            'remaining' => $maxAttempts - $this->limiter->attempts($key),
            'limit' => $maxAttempts,
            'reset_at' => now()->addMinutes($decayMinutes)->timestamp,
        ];
    }

    /**
     * Check rate limiting for sensitive operations.
     */
    public function checkSensitiveOperationLimit(Request $request, string $operation, ?string $userId = null): array
    {
        $identifier = $userId ?: ($request->user() ? $request->user()->getKey() : $request->ip());
        $key = "sensitive:{$operation}:{$identifier}";

        // Very restrictive limits for sensitive operations
        $limits = $this->getSensitiveOperationLimits($operation);

        if ($this->limiter->tooManyAttempts($key, $limits['attempts'])) {
            $this->logRateLimitViolation($request, 'sensitive_operation_limit', $operation);

            return [
                'allowed' => false,
                'operation' => $operation,
                'retry_after' => $this->limiter->availableIn($key),
                'message' => "Rate limit exceeded for operation: {$operation}",
            ];
        }

        $this->limiter->hit($key, $limits['window'] * 60);

        return [
            'allowed' => true,
            'remaining' => $limits['attempts'] - $this->limiter->attempts($key),
        ];
    }

    /**
     * Check for suspicious activity patterns.
     */
    public function checkSuspiciousActivity(Request $request): array
    {
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        // Check for rapid requests from same IP
        $rapidRequestKey = 'rapid:'.$ipAddress;
        if ($this->limiter->attempts($rapidRequestKey) > 50) { // 50 requests in 5 minutes
            $this->auditLog->logSuspiciousActivity(
                $request->user(),
                'rapid_requests',
                'Unusually high request rate detected',
                [
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'request_count' => $this->limiter->attempts($rapidRequestKey),
                ]
            );

            return [
                'suspicious' => true,
                'reason' => 'rapid_requests',
                'action' => 'throttle',
            ];
        }

        $this->limiter->hit($rapidRequestKey, 300); // 5 minute window

        // Check for unusual user agent patterns
        if ($this->isUnusualUserAgent($userAgent)) {
            $this->auditLog->logSuspiciousActivity(
                $request->user(),
                'unusual_user_agent',
                'Unusual user agent detected',
                [
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]
            );

            return [
                'suspicious' => true,
                'reason' => 'unusual_user_agent',
                'action' => 'monitor',
            ];
        }

        return ['suspicious' => false];
    }

    /**
     * Apply progressive rate limiting based on user behavior.
     */
    public function applyProgressiveRateLimit(Request $request, string $userId): array
    {
        $violationKey = "violations:{$userId}";
        $violations = $this->limiter->attempts($violationKey);

        // Progressive penalties
        if ($violations >= 5) {
            // Severe restrictions
            $maxAttempts = 10;
            $window = 3600; // 1 hour
        } elseif ($violations >= 3) {
            // Moderate restrictions
            $maxAttempts = 50;
            $window = 1800; // 30 minutes
        } elseif ($violations >= 1) {
            // Light restrictions
            $maxAttempts = 200;
            $window = 900; // 15 minutes
        } else {
            // Normal limits
            $maxAttempts = 1000;
            $window = 3600;
        }

        $key = "progressive:{$userId}";

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return [
                'allowed' => false,
                'progressive_level' => $violations,
                'retry_after' => $this->limiter->availableIn($key),
                'message' => 'Progressive rate limit applied due to previous violations.',
            ];
        }

        $this->limiter->hit($key, $window);

        return [
            'allowed' => true,
            'progressive_level' => $violations,
            'remaining' => $maxAttempts - $this->limiter->attempts($key),
        ];
    }

    /**
     * Record a rate limit violation.
     */
    public function recordViolation(Request $request, ?string $userId = null): void
    {
        $identifier = $userId ?: $request->ip();
        $key = "violations:{$identifier}";

        $this->limiter->hit($key, 86400); // 24 hour window

        $this->auditLog->logSecurityViolation(
            $request->user(),
            'rate_limit_violation',
            'Rate limit violation recorded',
            [
                'identifier' => $identifier,
                'total_violations' => $this->limiter->attempts($key),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );
    }

    /**
     * Get rate limiting status for monitoring.
     */
    public function getRateLimitStatus(Request $request): array
    {
        $identifier = $this->getApiIdentifier($request);
        $keys = [
            'api' => 'api:'.$identifier,
            'auth_ip' => 'auth_ip:'.$request->ip(),
            'rapid' => 'rapid:'.$request->ip(),
        ];

        $status = [];
        foreach ($keys as $type => $key) {
            $status[$type] = [
                'attempts' => $this->limiter->attempts($key),
                'remaining_time' => $this->limiter->availableIn($key),
            ];
        }

        return $status;
    }

    /**
     * Generate rate limit key for authentication.
     */
    private function getAuthRateLimitKey(Request $request, string $email): string
    {
        return 'auth:'.sha1(strtolower($email).'|'.$request->ip());
    }

    /**
     * Get API identifier for rate limiting.
     */
    private function getApiIdentifier(Request $request): string
    {
        // Use user ID if authenticated, otherwise use IP address
        if ($user = $request->user()) {
            return 'user:'.$user->getKey();
        }

        return 'ip:'.$request->ip();
    }

    /**
     * Get limits for sensitive operations.
     */
    private function getSensitiveOperationLimits(string $operation): array
    {
        $limits = [
            'delete_user' => ['attempts' => 5, 'window' => 60],
            'change_role' => ['attempts' => 10, 'window' => 60],
            'export_data' => ['attempts' => 3, 'window' => 60],
            'bulk_operation' => ['attempts' => 2, 'window' => 60],
            'password_reset' => ['attempts' => 3, 'window' => 15],
            '2fa_verification' => ['attempts' => 5, 'window' => 15],
            'admin_action' => ['attempts' => 20, 'window' => 60],
        ];

        return $limits[$operation] ?? ['attempts' => 5, 'window' => 60];
    }

    /**
     * Check if user agent appears unusual or suspicious.
     */
    private function isUnusualUserAgent(?string $userAgent): bool
    {
        if (! $userAgent) {
            return true;
        }

        // Check for common bot/scraper patterns
        $suspiciousPatterns = [
            '/bot/i',
            '/crawler/i',
            '/spider/i',
            '/scraper/i',
            '/curl/i',
            '/wget/i',
            '/python/i',
            '/postman/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }

        // Check if user agent is too short or generic
        if (strlen($userAgent) < 20 || Str::contains($userAgent, ['test', 'unknown', 'generic'])) {
            return true;
        }

        return false;
    }

    /**
     * Log rate limit violations.
     */
    private function logRateLimitViolation(Request $request, string $type, string $identifier): void
    {
        Log::warning('Rate limit violation', [
            'type' => $type,
            'identifier' => $identifier,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->url(),
            'method' => $request->method(),
        ]);

        $this->auditLog->logSecurityViolation(
            $request->user(),
            'rate_limit_exceeded',
            "Rate limit exceeded: {$type}",
            [
                'limit_type' => $type,
                'identifier' => $identifier,
                'url' => $request->url(),
                'method' => $request->method(),
            ]
        );
    }
}
