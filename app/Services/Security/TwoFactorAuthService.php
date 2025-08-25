<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\Central\CentralUser;
use App\Models\Tenant\TenantUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TwoFactorAuthService
{
    private const CODE_LENGTH = 6;

    private const CODE_EXPIRY_MINUTES = 5;

    private const MAX_ATTEMPTS = 3;

    private const LOCKOUT_MINUTES = 15;

    /**
     * Generate and send a 2FA code to the user.
     */
    public function generateAndSendCode(Model $user, string $method = 'email'): array
    {
        $userId = $this->getUserId($user);
        $userType = $this->getUserType($user);

        // Check if user is currently locked out
        if ($this->isUserLockedOut($userId, $userType)) {
            return [
                'success' => false,
                'message' => 'Too many failed attempts. Please try again later.',
                'lockout_remaining' => $this->getLockoutRemainingTime($userId, $userType),
            ];
        }

        // Generate a new code
        $code = $this->generateCode();
        $expiresAt = now()->addMinutes(self::CODE_EXPIRY_MINUTES);

        // Store the code
        $this->storeCode($userId, $userType, $code, $expiresAt);

        // Send the code
        $sent = $this->sendCode($user, $code, $method);

        if ($sent) {
            Log::info('2FA code generated and sent', [
                'user_id' => $userId,
                'user_type' => $userType,
                'method' => $method,
                'expires_at' => $expiresAt->toISOString(),
            ]);

            return [
                'success' => true,
                'message' => '2FA code sent successfully.',
                'expires_in' => self::CODE_EXPIRY_MINUTES * 60, // seconds
                'method' => $method,
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to send 2FA code. Please try again.',
        ];
    }

    /**
     * Verify a 2FA code provided by the user.
     */
    public function verifyCode(Model $user, string $providedCode): array
    {
        $userId = $this->getUserId($user);
        $userType = $this->getUserType($user);

        // Check if user is locked out
        if ($this->isUserLockedOut($userId, $userType)) {
            return [
                'success' => false,
                'message' => 'Account temporarily locked due to too many failed attempts.',
                'lockout_remaining' => $this->getLockoutRemainingTime($userId, $userType),
            ];
        }

        // Get stored code
        $storedData = $this->getStoredCode($userId, $userType);

        if (! $storedData) {
            $this->incrementFailedAttempts($userId, $userType);

            return [
                'success' => false,
                'message' => 'No valid 2FA code found. Please request a new one.',
            ];
        }

        // Check if code has expired
        if (now()->gt($storedData['expires_at'])) {
            $this->clearCode($userId, $userType);

            return [
                'success' => false,
                'message' => '2FA code has expired. Please request a new one.',
            ];
        }

        // Verify the code
        if (hash_equals($storedData['code'], $providedCode)) {
            // Success - clear the code and reset attempts
            $this->clearCode($userId, $userType);
            $this->clearFailedAttempts($userId, $userType);

            Log::info('2FA verification successful', [
                'user_id' => $userId,
                'user_type' => $userType,
            ]);

            return [
                'success' => true,
                'message' => '2FA verification successful.',
            ];
        }

        // Failed verification
        $this->incrementFailedAttempts($userId, $userType);
        $remainingAttempts = $this->getRemainingAttempts($userId, $userType);

        Log::warning('2FA verification failed', [
            'user_id' => $userId,
            'user_type' => $userType,
            'remaining_attempts' => $remainingAttempts,
        ]);

        if ($remainingAttempts <= 0) {
            $this->lockoutUser($userId, $userType);

            return [
                'success' => false,
                'message' => 'Too many failed attempts. Account temporarily locked.',
                'lockout_minutes' => self::LOCKOUT_MINUTES,
            ];
        }

        return [
            'success' => false,
            'message' => 'Invalid 2FA code.',
            'remaining_attempts' => $remainingAttempts,
        ];
    }

    /**
     * Check if 2FA is enabled for the user.
     */
    public function isEnabledForUser(Model $user): bool
    {
        // In a real implementation, this would check user preferences
        // For now, return based on user role or configuration
        if ($user instanceof CentralUser) {
            return in_array($user->role, ['admin', 'super_admin']);
        }

        if ($user instanceof TenantUser) {
            return $user->role === 'admin';
        }

        return false;
    }

    /**
     * Check if 2FA is required for the current context.
     */
    public function isRequiredForAction(Model $user, string $action): bool
    {
        $sensitiveActions = [
            'delete_user',
            'change_user_role',
            'access_admin_panel',
            'export_data',
            'change_security_settings',
        ];

        return $this->isEnabledForUser($user) && in_array($action, $sensitiveActions);
    }

    /**
     * Generate a random 2FA code.
     */
    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 10 ** self::CODE_LENGTH - 1), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Store the 2FA code in cache.
     */
    private function storeCode(string $userId, string $userType, string $code, \Carbon\Carbon $expiresAt): void
    {
        $key = $this->getCodeCacheKey($userId, $userType);

        Cache::put($key, [
            'code' => $code,
            'expires_at' => $expiresAt,
            'created_at' => now(),
        ], $expiresAt);
    }

    /**
     * Get stored 2FA code from cache.
     */
    private function getStoredCode(string $userId, string $userType): ?array
    {
        $key = $this->getCodeCacheKey($userId, $userType);

        return Cache::get($key);
    }

    /**
     * Clear the stored 2FA code.
     */
    private function clearCode(string $userId, string $userType): void
    {
        $key = $this->getCodeCacheKey($userId, $userType);
        Cache::forget($key);
    }

    /**
     * Send the 2FA code to the user.
     */
    private function sendCode(Model $user, string $code, string $method): bool
    {
        switch ($method) {
            case 'email':
                return $this->sendEmailCode($user, $code);
            case 'sms':
                return $this->sendSmsCode($user, $code);
            default:
                return false;
        }
    }

    /**
     * Send 2FA code via email.
     */
    private function sendEmailCode(Model $user, string $code): bool
    {
        try {
            // In a real implementation, you would use Laravel's Mail facade
            // Mail::to($user->email)->send(new TwoFactorCodeEmail($code));

            Log::info('2FA code email sent', [
                'recipient' => $user->email,
                'code' => $code, // Remove this in production!
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send 2FA email', [
                'user_id' => $this->getUserId($user),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send 2FA code via SMS.
     */
    private function sendSmsCode(Model $user, string $code): bool
    {
        try {
            // In a real implementation, you would use an SMS service
            // SMS::to($user->phone)->send("Your 2FA code: {$code}");

            Log::info('2FA code SMS sent', [
                'recipient' => $user->phone ?? 'N/A',
                'code' => $code, // Remove this in production!
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send 2FA SMS', [
                'user_id' => $this->getUserId($user),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Increment failed 2FA attempts.
     */
    private function incrementFailedAttempts(string $userId, string $userType): void
    {
        $key = $this->getAttemptsKey($userId, $userType);
        $attempts = Cache::get($key, 0) + 1;

        Cache::put($key, $attempts, now()->addMinutes(self::LOCKOUT_MINUTES));
    }

    /**
     * Get remaining 2FA attempts.
     */
    private function getRemainingAttempts(string $userId, string $userType): int
    {
        $key = $this->getAttemptsKey($userId, $userType);
        $attempts = Cache::get($key, 0);

        return max(0, self::MAX_ATTEMPTS - $attempts);
    }

    /**
     * Clear failed attempts counter.
     */
    private function clearFailedAttempts(string $userId, string $userType): void
    {
        $key = $this->getAttemptsKey($userId, $userType);
        Cache::forget($key);
    }

    /**
     * Lock out user temporarily.
     */
    private function lockoutUser(string $userId, string $userType): void
    {
        $key = $this->getLockoutKey($userId, $userType);
        Cache::put($key, now()->addMinutes(self::LOCKOUT_MINUTES), now()->addMinutes(self::LOCKOUT_MINUTES));
    }

    /**
     * Check if user is currently locked out.
     */
    private function isUserLockedOut(string $userId, string $userType): bool
    {
        $key = $this->getLockoutKey($userId, $userType);

        return Cache::has($key);
    }

    /**
     * Get remaining lockout time in seconds.
     */
    private function getLockoutRemainingTime(string $userId, string $userType): int
    {
        $key = $this->getLockoutKey($userId, $userType);
        $lockoutUntil = Cache::get($key);

        if (! $lockoutUntil) {
            return 0;
        }

        return max(0, $lockoutUntil->diffInSeconds(now()));
    }

    /**
     * Get cache key for 2FA code.
     */
    private function getCodeCacheKey(string $userId, string $userType): string
    {
        return "2fa_code:{$userType}:{$userId}";
    }

    /**
     * Get cache key for failed attempts.
     */
    private function getAttemptsKey(string $userId, string $userType): string
    {
        return "2fa_attempts:{$userType}:{$userId}";
    }

    /**
     * Get cache key for lockout.
     */
    private function getLockoutKey(string $userId, string $userType): string
    {
        return "2fa_lockout:{$userType}:{$userId}";
    }

    /**
     * Get user ID from model.
     */
    private function getUserId(Model $user): string
    {
        return (string) $user->getKey();
    }

    /**
     * Get user type from model.
     */
    private function getUserType(Model $user): string
    {
        if ($user instanceof CentralUser) {
            return 'central';
        }

        if ($user instanceof TenantUser) {
            return 'tenant';
        }

        return 'unknown';
    }
}
