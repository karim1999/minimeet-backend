<?php

namespace App\Services\Central;

use App\Models\Central\CentralUserActivity;
use App\Models\Central\TenantUserManagement;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDashboardService
{
    /**
     * Get platform overview statistics.
     */
    public function getOverviewStats(): array
    {
        $totalTenants = Tenant::count();
        $totalUsers = TenantUserManagement::sum('user_count') ?: 0;
        $activeUsers = TenantUserManagement::sum('active_users') ?: 0;
        $activeTenants = Tenant::whereHas('userManagement', function ($query) {
            $query->where('last_activity_at', '>=', now()->subDays(7));
        })->count();

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'new_users_today' => CentralUserActivity::whereDate('created_at', now())
                ->where('action', 'like', '%user_created%')
                ->count(),
            'total_tenants' => $totalTenants,
            'active_tenants' => $activeTenants,
            'recent_activities' => CentralUserActivity::where('created_at', '>=', now()->subDay())->count(),

            // Legacy format for backward compatibility
            'tenants' => [
                'total' => $totalTenants,
                'active' => $activeTenants,
                'growth' => $this->getTenantGrowth(),
            ],
            'users' => [
                'total' => $totalUsers,
                'active' => $activeUsers,
                'growth' => $this->getUserGrowth(),
            ],
            'activities' => [
                'today' => CentralUserActivity::whereDate('created_at', now())->count(),
                'this_week' => CentralUserActivity::where('created_at', '>=', now()->startOfWeek())->count(),
                'this_month' => CentralUserActivity::where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'system' => [
                'uptime' => $this->getSystemUptime(),
                'memory_usage' => $this->getMemoryUsage(),
                'database_size' => $this->getDatabaseSize(),
            ],
        ];
    }

    /**
     * Get tenant statistics.
     */
    public function getTenantStats(): Collection
    {
        return Tenant::with(['userManagement', 'domains'])
            ->get()
            ->map(function ($tenant) {
                $userManagement = $tenant->userManagement;

                return [
                    'id' => $tenant->id,
                    'created_at' => $tenant->created_at,
                    'domains' => $tenant->domains->pluck('domain'),
                    'user_count' => $userManagement?->user_count ?? 0,
                    'active_users' => $userManagement?->active_users ?? 0,
                    'last_activity' => $userManagement?->last_activity_at,
                    'activity_status' => $userManagement?->getActivityStatus() ?? 'inactive',
                    'growth_rate' => $userManagement?->getUserGrowthRate() ?? 0,
                ];
            });
    }

    /**
     * Get user statistics across all tenants.
     */
    public function getUserStats(): array
    {
        $userManagement = TenantUserManagement::all();

        return [
            'total_users' => $userManagement->sum('user_count'),
            'active_users' => $userManagement->sum('active_users'),
            'avg_users_per_tenant' => $userManagement->avg('user_count'),
            'most_active_tenant' => $this->getMostActiveTenant(),
            'user_distribution' => $this->getUserDistribution(),
            'activity_timeline' => $this->getUserActivityTimeline(),
        ];
    }

    /**
     * Get recent users across all tenants.
     */
    public function getRecentUsers(int $limit = 10): array
    {
        // This is a simplified version - in a real implementation,
        // you'd need to query across tenant databases or maintain
        // a consolidated user view in the central database
        return TenantUserManagement::with('tenant')
            ->latest('updated_at')
            ->take($limit)
            ->get()
            ->map(function ($management) {
                return [
                    'name' => 'Recent User', // Placeholder - would need actual user data
                    'email' => 'user@'.($management->tenant->domains->first()?->domain ?? 'unknown.com'),
                    'tenant_name' => $management->tenant->name ?? 'Unknown Tenant',
                    'is_active' => $management->active_users > 0,
                    'role' => 'user', // Placeholder
                    'created_at' => $management->updated_at,
                ];
            })
            ->toArray();
    }

    /**
     * Get recent platform activities.
     */
    public function getRecentActivities(int $limit = 50): Collection
    {
        return CentralUserActivity::with(['user'])
            ->latest()
            ->take($limit)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'action' => $activity->formatted_action,
                    'description' => $activity->description,
                    'user' => [
                        'id' => $activity->user->id,
                        'name' => $activity->user->name,
                        'role' => $activity->user->role,
                    ],
                    'metadata' => $activity->metadata,
                    'ip_address' => $activity->ip_address,
                    'created_at' => $activity->created_at->toISOString(),
                    'time_ago' => $activity->created_at->diffForHumans(),
                ];
            });
    }

    /**
     * Get system health information.
     */
    public function getSystemHealth(): array
    {
        return [
            'database' => $this->checkDatabaseHealth(),
            'cache' => $this->checkCacheHealth(),
            'queue' => $this->checkQueueHealth(),
            'storage' => $this->checkStorageHealth(),
        ];
    }

    /**
     * Get metrics for specific timeframe and metric type.
     */
    public function getMetrics(string $timeframe, string $metric): array
    {
        $startDate = $this->getStartDateForTimeframe($timeframe);

        return match ($metric) {
            'users' => $this->getUserMetrics($startDate),
            'tenants' => $this->getTenantMetrics($startDate),
            'activities' => $this->getActivityMetrics($startDate),
            'logins' => $this->getLoginMetrics($startDate),
            default => [],
        };
    }

    /**
     * Export dashboard data.
     */
    public function exportData(string $type, string $format): StreamedResponse
    {
        $headers = [
            'Content-Type' => $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$type}_export_{$format}\"",
        ];

        return new StreamedResponse(function () use ($type, $format) {
            $handle = fopen('php://output', 'w');

            match ($type) {
                'tenants' => $this->exportTenants($handle, $format),
                'users' => $this->exportUsers($handle, $format),
                'activities' => $this->exportActivities($handle, $format),
            };

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Get tenant growth rate.
     */
    private function getTenantGrowth(): float
    {
        $currentMonth = Tenant::whereMonth('created_at', now()->month)->count();
        $lastMonth = Tenant::whereMonth('created_at', now()->subMonth()->month)->count();

        if ($lastMonth === 0) {
            return $currentMonth > 0 ? 100.0 : 0.0;
        }

        return (($currentMonth - $lastMonth) / $lastMonth) * 100;
    }

    /**
     * Get user growth rate.
     */
    private function getUserGrowth(): float
    {
        $currentStats = TenantUserManagement::whereMonth('updated_at', now()->month)->sum('user_count');
        $lastMonthStats = TenantUserManagement::whereMonth('updated_at', now()->subMonth()->month)->sum('user_count');

        if ($lastMonthStats === 0) {
            return $currentStats > 0 ? 100.0 : 0.0;
        }

        return (($currentStats - $lastMonthStats) / $lastMonthStats) * 100;
    }

    /**
     * Get system uptime in hours.
     */
    private function getSystemUptime(): float
    {
        // This is a placeholder - in production you'd get actual system uptime
        return 99.9;
    }

    /**
     * Get memory usage percentage.
     */
    private function getMemoryUsage(): float
    {
        return memory_get_usage(true) / 1024 / 1024; // MB
    }

    /**
     * Get database size in MB.
     */
    private function getDatabaseSize(): float
    {
        // This is a placeholder - in production you'd query actual DB size
        return 150.5;
    }

    /**
     * Get most active tenant.
     */
    private function getMostActiveTenant(): ?array
    {
        $mostActive = TenantUserManagement::orderBy('active_users', 'desc')->first();

        if (! $mostActive) {
            return null;
        }

        return [
            'tenant_id' => $mostActive->tenant_id,
            'active_users' => $mostActive->active_users,
            'last_activity' => $mostActive->last_activity_at,
        ];
    }

    /**
     * Get user distribution by tenant size.
     */
    private function getUserDistribution(): array
    {
        $userCounts = TenantUserManagement::pluck('user_count');

        return [
            'small' => $userCounts->filter(fn ($count) => $count <= 10)->count(),
            'medium' => $userCounts->filter(fn ($count) => $count > 10 && $count <= 50)->count(),
            'large' => $userCounts->filter(fn ($count) => $count > 50)->count(),
        ];
    }

    /**
     * Get user activity timeline.
     */
    private function getUserActivityTimeline(): array
    {
        return CentralUserActivity::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();
    }

    /**
     * Check database health.
     */
    private function checkDatabaseHealth(): array
    {
        try {
            \DB::connection()->getPdo();

            return ['status' => 'healthy', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    /**
     * Check cache health.
     */
    private function checkCacheHealth(): array
    {
        try {
            \Cache::put('health_check', 'ok', 5);
            $value = \Cache::get('health_check');

            return ['status' => $value === 'ok' ? 'healthy' : 'unhealthy', 'message' => 'Cache working'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    /**
     * Check queue health.
     */
    private function checkQueueHealth(): array
    {
        // Placeholder for queue health check
        return ['status' => 'healthy', 'message' => 'Queue system operational'];
    }

    /**
     * Check storage health.
     */
    private function checkStorageHealth(): array
    {
        try {
            $diskSpace = disk_free_space('/');
            $totalSpace = disk_total_space('/');
            $usagePercent = (($totalSpace - $diskSpace) / $totalSpace) * 100;

            return [
                'status' => $usagePercent < 90 ? 'healthy' : 'warning',
                'usage_percent' => round($usagePercent, 2),
                'free_space' => $this->formatBytes($diskSpace),
            ];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    /**
     * Get start date for timeframe.
     */
    private function getStartDateForTimeframe(string $timeframe): \Carbon\Carbon
    {
        return match ($timeframe) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subDay(),
        };
    }

    /**
     * Get user metrics for timeframe.
     */
    private function getUserMetrics(\Carbon\Carbon $startDate): array
    {
        return TenantUserManagement::where('updated_at', '>=', $startDate)
            ->selectRaw('DATE(updated_at) as date, SUM(user_count) as total_users, SUM(active_users) as active_users')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Get tenant metrics for timeframe.
     */
    private function getTenantMetrics(\Carbon\Carbon $startDate): array
    {
        return Tenant::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as new_tenants')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('new_tenants', 'date')
            ->toArray();
    }

    /**
     * Get activity metrics for timeframe.
     */
    private function getActivityMetrics(\Carbon\Carbon $startDate): array
    {
        return CentralUserActivity::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as activity_count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('activity_count', 'date')
            ->toArray();
    }

    /**
     * Get login metrics for timeframe.
     */
    private function getLoginMetrics(\Carbon\Carbon $startDate): array
    {
        return CentralUserActivity::where('created_at', '>=', $startDate)
            ->where('action', 'admin_login')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as login_count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('login_count', 'date')
            ->toArray();
    }

    /**
     * Export tenants data.
     */
    private function exportTenants($handle, string $format): void
    {
        if ($format === 'csv') {
            fputcsv($handle, ['ID', 'Created At', 'Domains', 'User Count', 'Active Users', 'Last Activity']);
        }

        $this->getTenantStats()->each(function ($tenant) use ($handle, $format) {
            if ($format === 'csv') {
                fputcsv($handle, [
                    $tenant['id'],
                    $tenant['created_at'],
                    implode(';', $tenant['domains']),
                    $tenant['user_count'],
                    $tenant['active_users'],
                    $tenant['last_activity'],
                ]);
            }
        });
    }

    /**
     * Export users data.
     */
    private function exportUsers($handle, string $format): void
    {
        // Placeholder for user export logic
    }

    /**
     * Export activities data.
     */
    private function exportActivities($handle, string $format): void
    {
        // Placeholder for activity export logic
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor]);
    }
}
