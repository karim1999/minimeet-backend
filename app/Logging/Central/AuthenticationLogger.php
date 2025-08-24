<?php

namespace App\Logging\Central;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class AuthenticationLogger
{
    private const LOG_CHANNEL = 'auth';

    /**
     * Log successful login attempt.
     */
    public function logSuccessfulLogin(
        User $user,
        Request $request,
        string $authMethod = 'session'
    ): void {
        $this->log('info', 'Successful login', [
            'event' => 'login_success',
            'user_id' => $user->id,
            'email' => $user->email,
            'auth_method' => $authMethod,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
        ]);
    }

    /**
     * Log failed login attempt.
     */
    public function logFailedLogin(
        string $email,
        Request $request,
        string $reason = 'invalid_credentials'
    ): void {
        $this->log('warning', 'Failed login attempt', [
            'event' => 'login_failed',
            'email' => $email,
            'reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log user logout.
     */
    public function logLogout(
        User $user,
        Request $request,
        string $authMethod = 'session'
    ): void {
        $this->log('info', 'User logout', [
            'event' => 'logout',
            'user_id' => $user->id,
            'email' => $user->email,
            'auth_method' => $authMethod,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log token creation.
     */
    public function logTokenCreated(
        User $user,
        PersonalAccessToken $token,
        Request $request
    ): void {
        $this->log('info', 'API token created', [
            'event' => 'token_created',
            'user_id' => $user->id,
            'email' => $user->email,
            'token_id' => $token->id,
            'token_name' => $token->name,
            'token_abilities' => $token->abilities,
            'expires_at' => $token->expires_at?->toISOString(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log token revocation.
     */
    public function logTokenRevoked(
        User $user,
        int $tokenId,
        Request $request,
        string $reason = 'user_requested'
    ): void {
        $this->log('info', 'API token revoked', [
            'event' => 'token_revoked',
            'user_id' => $user->id,
            'email' => $user->email,
            'token_id' => $tokenId,
            'reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log account lockout.
     */
    public function logAccountLockout(
        string $email,
        Request $request,
        int $attemptCount,
        int $lockoutDuration
    ): void {
        $this->log('alert', 'Account locked due to failed attempts', [
            'event' => 'account_locked',
            'email' => $email,
            'failed_attempts' => $attemptCount,
            'lockout_duration_minutes' => $lockoutDuration,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log suspicious activity.
     */
    public function logSuspiciousActivity(
        string $activity,
        array $context,
        Request $request,
        ?User $user = null
    ): void {
        $logData = [
            'event' => 'suspicious_activity',
            'activity' => $activity,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
            'context' => $context,
        ];

        if ($user) {
            $logData['user_id'] = $user->id;
            $logData['email'] = $user->email;
        }

        $this->log('alert', 'Suspicious activity detected', $logData);
    }

    /**
     * Log password validation failure.
     */
    public function logPasswordValidationFailure(
        string $email,
        array $validationErrors,
        Request $request
    ): void {
        $this->log('info', 'Password validation failed', [
            'event' => 'password_validation_failed',
            'email' => $email,
            'validation_errors' => $validationErrors,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log session security events.
     */
    public function logSessionSecurityEvent(
        string $event,
        Request $request,
        ?User $user = null,
        array $context = []
    ): void {
        $logData = [
            'event' => 'session_security',
            'security_event' => $event,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'timestamp' => now()->toISOString(),
            'context' => $context,
        ];

        if ($user) {
            $logData['user_id'] = $user->id;
            $logData['email'] = $user->email;
        }

        $level = in_array($event, ['hijack_attempt', 'session_fixation']) ? 'alert' : 'warning';

        $this->log($level, "Session security event: {$event}", $logData);
    }

    /**
     * Log authentication method changes.
     */
    public function logAuthMethodChange(
        User $user,
        string $fromMethod,
        string $toMethod,
        Request $request
    ): void {
        $this->log('info', 'Authentication method changed', [
            'event' => 'auth_method_changed',
            'user_id' => $user->id,
            'email' => $user->email,
            'from_method' => $fromMethod,
            'to_method' => $toMethod,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log mass token operations (security concern).
     */
    public function logMassTokenOperation(
        User $user,
        string $operation,
        int $affectedCount,
        Request $request
    ): void {
        $this->log('warning', 'Mass token operation performed', [
            'event' => 'mass_token_operation',
            'user_id' => $user->id,
            'email' => $user->email,
            'operation' => $operation,
            'affected_tokens' => $affectedCount,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get authentication log statistics.
     */
    public function getLogStatistics(int $days = 7): array
    {
        // This would typically query log files or a logging service
        // For now, return placeholder data
        return [
            'period_days' => $days,
            'total_events' => 0,
            'login_success' => 0,
            'login_failed' => 0,
            'logout_events' => 0,
            'token_operations' => 0,
            'security_alerts' => 0,
            'suspicious_activities' => 0,
        ];
    }

    /**
     * Create structured log entry.
     */
    private function log(string $level, string $message, array $context = []): void
    {
        // Add standard context information
        $standardContext = [
            'application' => 'minimeet-backend',
            'component' => 'authentication',
            'environment' => app()->environment(),
        ];

        $fullContext = array_merge($standardContext, $context);

        Log::channel(static::LOG_CHANNEL)->log($level, $message, $fullContext);
    }
}
