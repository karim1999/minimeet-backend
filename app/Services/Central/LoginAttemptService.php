<?php

namespace App\Services\Central;

use App\Models\Central\LoginAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LoginAttemptService
{
    /**
     * Record a login attempt.
     */
    public function recordAttempt(
        Request $request,
        string $email,
        bool $success,
        ?User $user = null
    ): LoginAttempt {
        $attempt = LoginAttempt::create([
            'user_id' => $user?->id,
            'email' => $email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent() ?? '',
            'success' => $success,
            'attempted_at' => now(),
        ]);

        // Log the attempt for monitoring
        $this->logAttempt($attempt, $request);

        return $attempt;
    }

    /**
     * Check if an email/IP combination is locked out.
     */
    public function isLockedOut(string $email, string $ip): bool
    {
        $maxAttempts = config('auth.login_attempts.max_attempts', 5);
        $lockoutMinutes = config('auth.login_attempts.lockout_minutes', 15);

        return LoginAttempt::isLockedOut($email, $ip, $maxAttempts, $lockoutMinutes);
    }

    /**
     * Get the number of recent failed attempts for an email.
     */
    public function getRecentFailedAttempts(string $email, int $minutes = 15): int
    {
        return LoginAttempt::getRecentFailedAttempts($email, $minutes);
    }

    /**
     * Get the number of recent failed attempts from an IP.
     */
    public function getRecentFailedAttemptsFromIp(string $ip, int $minutes = 15): int
    {
        return LoginAttempt::getRecentFailedAttemptsFromIp($ip, $minutes);
    }

    /**
     * Get time until lockout expires for an email.
     */
    public function getLockoutTimeRemaining(string $email, string $ip): ?int
    {
        $lockoutMinutes = config('auth.login_attempts.lockout_minutes', 15);

        $lastFailedEmailAttempt = LoginAttempt::forEmail($email)
            ->failed()
            ->orderBy('attempted_at', 'desc')
            ->first();

        $lastFailedIpAttempt = LoginAttempt::fromIp($ip)
            ->failed()
            ->orderBy('attempted_at', 'desc')
            ->first();

        $lastAttempt = null;

        if ($lastFailedEmailAttempt && $lastFailedIpAttempt) {
            $lastAttempt = $lastFailedEmailAttempt->attempted_at->gt($lastFailedIpAttempt->attempted_at)
                ? $lastFailedEmailAttempt
                : $lastFailedIpAttempt;
        } elseif ($lastFailedEmailAttempt) {
            $lastAttempt = $lastFailedEmailAttempt;
        } elseif ($lastFailedIpAttempt) {
            $lastAttempt = $lastFailedIpAttempt;
        }

        if (! $lastAttempt) {
            return null;
        }

        $lockoutExpiresAt = $lastAttempt->attempted_at->addMinutes($lockoutMinutes);

        if (now()->gt($lockoutExpiresAt)) {
            return null; // Lockout has expired
        }

        return now()->diffInSeconds($lockoutExpiresAt);
    }

    /**
     * Clear old login attempts (for cleanup).
     */
    public function clearOldAttempts(int $daysToKeep = 30): int
    {
        $cutoffDate = now()->subDays($daysToKeep);

        return LoginAttempt::where('attempted_at', '<', $cutoffDate)->delete();
    }

    /**
     * Get login attempt statistics.
     */
    public function getStatistics(int $days = 7): array
    {
        $startDate = now()->subDays($days)->startOfDay();

        $total = LoginAttempt::where('attempted_at', '>=', $startDate)->count();
        $successful = LoginAttempt::where('attempted_at', '>=', $startDate)
            ->where('success', true)
            ->count();
        $failed = $total - $successful;

        $uniqueEmails = LoginAttempt::where('attempted_at', '>=', $startDate)
            ->distinct('email')
            ->count();

        $uniqueIps = LoginAttempt::where('attempted_at', '>=', $startDate)
            ->distinct('ip_address')
            ->count();

        return [
            'period_days' => $days,
            'total_attempts' => $total,
            'successful_attempts' => $successful,
            'failed_attempts' => $failed,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            'unique_emails' => $uniqueEmails,
            'unique_ips' => $uniqueIps,
        ];
    }

    /**
     * Get top failed IP addresses.
     */
    public function getTopFailedIps(int $limit = 10, int $days = 7): array
    {
        $startDate = now()->subDays($days)->startOfDay();

        return LoginAttempt::where('attempted_at', '>=', $startDate)
            ->where('success', false)
            ->selectRaw('ip_address, COUNT(*) as failed_count')
            ->groupBy('ip_address')
            ->orderBy('failed_count', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Log the login attempt for monitoring purposes.
     */
    private function logAttempt(LoginAttempt $attempt, Request $request): void
    {
        $logData = [
            'email' => $attempt->email,
            'ip_address' => $attempt->ip_address,
            'success' => $attempt->success,
            'user_agent' => $attempt->user_agent,
            'timestamp' => $attempt->attempted_at->toISOString(),
        ];

        if ($attempt->success) {
            Log::info('Successful login attempt', $logData);
        } else {
            Log::warning('Failed login attempt', $logData);
        }

        // Additional monitoring for suspicious activity
        if (! $attempt->success) {
            $recentFailures = $this->getRecentFailedAttempts($attempt->email);
            $recentIpFailures = $this->getRecentFailedAttemptsFromIp($attempt->ip_address);

            if ($recentFailures >= 3 || $recentIpFailures >= 5) {
                Log::alert('Multiple failed login attempts detected', [
                    'email' => $attempt->email,
                    'ip_address' => $attempt->ip_address,
                    'recent_email_failures' => $recentFailures,
                    'recent_ip_failures' => $recentIpFailures,
                ]);
            }
        }
    }
}
