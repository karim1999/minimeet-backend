<?php

namespace App\Services\Central;

use App\Models\Central\CentralUser;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class AdminAuthenticationService
{
    /**
     * Authenticate a central admin user.
     *
     * @throws AuthenticationException
     */
    public function authenticate(array $credentials): array
    {
        $email = $credentials['email'] ?? '';
        $password = $credentials['password'] ?? '';

        // Find user in central database
        $user = CentralUser::where('email', $email)
            ->where('is_central', true)
            ->whereIn('role', ['super_admin', 'admin', 'support'])
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        // Create session
        $this->createSession($user);

        // Update last login
        $user->updateLastLogin();

        // Log successful authentication
        $user->logActivity('admin_login');

        return [
            'user' => $user,
            'token' => $user->createToken('admin-session')->plainTextToken,
        ];
    }

    /**
     * Create an admin session.
     */
    public function createSession(CentralUser $user): void
    {
        Auth::guard('web')->login($user, true);

        // Regenerate session ID for security
        Session::regenerate();

        // Store additional session data
        Session::put([
            'admin_login_at' => now(),
            'admin_role' => $user->role,
        ]);
    }

    /**
     * Invalidate admin session.
     */
    public function invalidateSession(): void
    {
        $user = Auth::guard('web')->user();

        if ($user) {
            // Log logout activity
            $user->logActivity('admin_logout');

            // Revoke all tokens
            $user->tokens()->delete();
        }

        // Logout and invalidate session
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();
    }

    /**
     * Verify two-factor authentication code.
     */
    public function verifyTwoFactor(CentralUser $user, string $code): bool
    {
        // This will be implemented when 2FA is added
        // For now, return true to allow development
        return true;
    }

    /**
     * Log activity for a central user.
     */
    public function logActivity(CentralUser $user, string $action, array $metadata = []): void
    {
        $user->logActivity($action, null, array_merge($metadata, [
            'session_id' => Session::getId(),
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
        ]));
    }

    /**
     * Check if user session is valid and not expired.
     */
    public function isSessionValid(): bool
    {
        if (! Auth::guard('web')->check()) {
            return false;
        }

        $loginAt = Session::get('admin_login_at');
        if (! $loginAt) {
            return false;
        }

        // Check if session has expired (30 minutes of inactivity)
        $timeout = config('session.lifetime', 30) * 60;

        if (now()->diffInSeconds($loginAt) > $timeout) {
            $this->invalidateSession();

            return false;
        }

        return true;
    }

    /**
     * Refresh session activity timestamp.
     */
    public function refreshSession(): void
    {
        if (Auth::guard('web')->check()) {
            Session::put('last_activity', now());
        }
    }

    /**
     * Get current authenticated admin user.
     */
    public function getCurrentUser(): ?CentralUser
    {
        return Auth::guard('web')->user();
    }

    /**
     * Check if current user has permission for action.
     */
    public function canPerformAction(string $action): bool
    {
        $user = $this->getCurrentUser();

        if (! $user) {
            return false;
        }

        return match ($action) {
            'create_tenant' => $user->isSuperAdmin(),
            'delete_tenant' => $user->isSuperAdmin(),
            'manage_users' => $user->isAdmin(),
            'view_statistics' => $user->isAdmin(),
            default => false,
        };
    }
}
