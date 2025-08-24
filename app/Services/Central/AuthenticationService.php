<?php

namespace App\Services\Central;

use App\Http\Resources\Central\UserResource;
use App\Logging\Central\AuthenticationLogger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthenticationService
{
    public function __construct(
        private readonly LoginAttemptService $loginAttemptService,
        private readonly AuthenticationLogger $authLogger
    ) {}

    /**
     * Attempt to authenticate a user with the provided credentials.
     */
    public function authenticateUser(
        Request $request,
        array $credentials
    ): array {
        $email = $credentials['email'];
        $ip = $request->ip();

        // Check if user is locked out
        if ($this->loginAttemptService->isLockedOut($email, $ip)) {
            $timeRemaining = $this->loginAttemptService->getLockoutTimeRemaining($email, $ip);

            // Log lockout attempt
            $this->authLogger->logFailedLogin($email, $request, 'account_locked');

            return [
                'success' => false,
                'lockout' => true,
                'lockout_seconds_remaining' => $timeRemaining,
                'message' => 'Too many failed login attempts. Please try again later.',
            ];
        }

        // Attempt authentication
        if (Auth::guard('web')->attempt($credentials)) {
            $user = Auth::guard('web')->user();

            // Record successful login attempt
            $this->loginAttemptService->recordAttempt($request, $email, true, $user);

            // Log successful authentication
            $this->authLogger->logSuccessfulLogin($user, $request, 'session');

            // Handle session regeneration
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }

            return [
                'success' => true,
                'user' => $user,
                'message' => 'Logged in successfully',
            ];
        }

        // Record failed login attempt
        $this->loginAttemptService->recordAttempt($request, $email, false);

        // Log failed authentication
        $this->authLogger->logFailedLogin($email, $request, 'invalid_credentials');

        return [
            'success' => false,
            'lockout' => false,
            'message' => 'The provided credentials are incorrect.',
        ];
    }

    /**
     * Verify user credentials without authentication.
     */
    public function verifyCredentials(string $email, string $password): bool
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return false;
        }

        return Hash::check($password, $user->password);
    }

    /**
     * Get user information with additional authentication context.
     */
    public function getUserWithContext(User $user): array
    {
        return [
            'user' => new UserResource($user),
            'authentication_method' => $this->getAuthenticationMethod(),
            'session_info' => $this->getSessionInfo(),
        ];
    }

    /**
     * Determine the current authentication method.
     */
    private function getAuthenticationMethod(): string
    {
        if (Auth::guard('central_sanctum')->check()) {
            return 'token';
        } elseif (Auth::guard('web')->check()) {
            return 'session';
        }

        return 'none';
    }

    /**
     * Get current session information.
     */
    private function getSessionInfo(): ?array
    {
        if (! Auth::guard('web')->check()) {
            return null;
        }

        return [
            'guard' => 'web',
            'session_lifetime' => config('session.lifetime'),
            'last_activity' => now()->toISOString(),
        ];
    }

    /**
     * Validate authentication requirements.
     */
    public function validateAuthenticationAttempt(
        Request $request,
        array $credentials
    ): array {
        $errors = [];

        // Validate email format
        if (! filter_var($credentials['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = ['Invalid email format.'];
        }

        // Check for empty password
        if (empty($credentials['password'])) {
            $errors['password'] = ['Password is required.'];
        }

        // Check rate limiting
        $email = $credentials['email'];
        $ip = $request->ip();

        if ($this->loginAttemptService->isLockedOut($email, $ip)) {
            $errors['general'] = ['Account temporarily locked due to multiple failed attempts.'];
        }

        return $errors;
    }

    /**
     * Get authentication statistics for monitoring.
     */
    public function getAuthenticationStatistics(int $days = 7): array
    {
        return [
            'login_attempts' => $this->loginAttemptService->getStatistics($days),
            'authentication_methods' => $this->getAuthenticationMethodStats($days),
            'session_info' => $this->getSessionStatistics(),
        ];
    }

    /**
     * Get authentication method statistics.
     */
    private function getAuthenticationMethodStats(int $days): array
    {
        // This would need to be implemented with proper tracking
        // For now, return placeholder data
        return [
            'session_logins' => 0,
            'token_authentications' => 0,
            'period_days' => $days,
        ];
    }

    /**
     * Get session-related statistics.
     */
    private function getSessionStatistics(): array
    {
        return [
            'default_lifetime_minutes' => config('session.lifetime'),
            'driver' => config('session.driver'),
            'secure_cookies' => config('session.secure'),
        ];
    }

    /**
     * Clean up old authentication data.
     */
    public function performCleanup(): array
    {
        $results = [];

        // Clean up old login attempts
        $results['login_attempts_cleaned'] = $this->loginAttemptService->clearOldAttempts();

        // Additional cleanup tasks can be added here

        return $results;
    }
}
