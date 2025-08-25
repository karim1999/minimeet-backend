<?php

namespace App\Models\Central;

use App\Models\Tenant;
use Database\Factories\TenantUserManagementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUserManagement extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $table = 'tenant_users_management';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_count',
        'active_users',
        'admin_users',
        'manager_users',
        'regular_users',
        'new_users_30d',
        'recently_active_users',
        'total_activities',
        'recent_activities_24h',
        'recent_logins_7d',
        'last_activity_at',
        'activity_breakdown',
        'most_active_users',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'metadata' => 'array',
            'activity_breakdown' => 'array',
            'most_active_users' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): TenantUserManagementFactory
    {
        return TenantUserManagementFactory::new();
    }

    /**
     * Get the tenant that owns this user management record.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Update the statistics for this tenant.
     */
    public function updateStats(): void
    {
        // Switch to tenant context to count users
        $tenant = $this->tenant;

        tenancy()->initialize($tenant);

        try {
            $userCount = \DB::table('users')->count();
            $activeUsers = \DB::table('users')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->count();

            $lastActivity = \DB::table('user_activities')
                ->orderBy('created_at', 'desc')
                ->value('created_at');

            $this->update([
                'user_count' => $userCount,
                'active_users' => $activeUsers,
                'last_activity_at' => $lastActivity,
            ]);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Get the active user count.
     */
    public function getActiveUserCount(): int
    {
        return $this->active_users;
    }

    /**
     * Get the total user count.
     */
    public function getTotalUserCount(): int
    {
        return $this->user_count;
    }

    /**
     * Get the user growth rate.
     */
    public function getUserGrowthRate(): float
    {
        $previousRecord = static::where('tenant_id', $this->tenant_id)
            ->where('created_at', '<', $this->created_at)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $previousRecord || $previousRecord->user_count === 0) {
            return 0.0;
        }

        return (($this->user_count - $previousRecord->user_count) / $previousRecord->user_count) * 100;
    }

    /**
     * Check if tenant has recent activity.
     */
    public function hasRecentActivity(int $days = 7): bool
    {
        return $this->last_activity_at &&
               $this->last_activity_at->isAfter(now()->subDays($days));
    }

    /**
     * Get tenant activity status.
     */
    public function getActivityStatus(): string
    {
        if (! $this->last_activity_at) {
            return 'inactive';
        }

        $daysSinceActivity = $this->last_activity_at->diffInDays(now());

        return match (true) {
            $daysSinceActivity === 0 => 'very_active',
            $daysSinceActivity <= 3 => 'active',
            $daysSinceActivity <= 7 => 'moderate',
            $daysSinceActivity <= 30 => 'low',
            default => 'inactive'
        };
    }
}
