<?php

namespace App\Models\Central;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginAttempt extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'email',
        'ip_address',
        'user_agent',
        'success',
        'attempted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'attempted_at' => 'datetime',
        ];
    }

    /**
     * Get the user that made the login attempt.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter successful attempts.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * Scope to filter failed attempts.
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Scope to filter attempts for a specific email.
     */
    public function scopeForEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    /**
     * Scope to filter attempts from a specific IP address.
     */
    public function scopeFromIp($query, string $ip)
    {
        return $query->where('ip_address', $ip);
    }

    /**
     * Scope to filter attempts within a time range.
     */
    public function scopeWithinTimeRange($query, \DateTime $start, \DateTime $end)
    {
        return $query->whereBetween('attempted_at', [$start, $end]);
    }

    /**
     * Scope to filter recent attempts (within last N minutes).
     */
    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('attempted_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * Get recent failed attempts for an email.
     */
    public static function getRecentFailedAttempts(string $email, int $minutes = 15): int
    {
        return static::forEmail($email)
            ->failed()
            ->recent($minutes)
            ->count();
    }

    /**
     * Get recent failed attempts from an IP address.
     */
    public static function getRecentFailedAttemptsFromIp(string $ip, int $minutes = 15): int
    {
        return static::fromIp($ip)
            ->failed()
            ->recent($minutes)
            ->count();
    }

    /**
     * Check if an email or IP is currently locked out.
     */
    public static function isLockedOut(string $email, string $ip, int $maxAttempts = 5, int $lockoutMinutes = 15): bool
    {
        $emailAttempts = static::getRecentFailedAttempts($email, $lockoutMinutes);
        $ipAttempts = static::getRecentFailedAttemptsFromIp($ip, $lockoutMinutes);

        return $emailAttempts >= $maxAttempts || $ipAttempts >= $maxAttempts;
    }
}
