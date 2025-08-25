<?php

declare(strict_types=1);

namespace App\Jobs\Central;

use App\Models\Central\TenantUserManagement;
use App\Models\Tenant;
use App\Models\Tenant\TenantUser;
use App\Models\Tenant\TenantUserActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateTenantStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        private readonly string $tenantId
    ) {
        // Force this job to run on the central queue
        $this->onConnection('central');
    }

    public function handle(): void
    {
        try {
            $tenant = Tenant::findOrFail($this->tenantId);

            Log::info('Updating tenant statistics', [
                'tenant_id' => $tenant->id,
            ]);

            // Initialize tenancy for this tenant to access their data
            tenancy()->initialize($tenant);

            // Gather user statistics from tenant database
            $userStats = $this->gatherUserStats();

            // Gather activity statistics
            $activityStats = $this->gatherActivityStats();

            // End tenancy context
            tenancy()->end();

            // Update or create tenant management record in central database
            $this->updateTenantManagement($tenant, $userStats, $activityStats);

            Log::info('Tenant statistics updated successfully', [
                'tenant_id' => $tenant->id,
                'user_count' => $userStats['total_users'],
                'active_users' => $userStats['active_users'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update tenant statistics', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Tenant stats update job failed permanently', [
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
        ]);
    }

    private function gatherUserStats(): array
    {
        $totalUsers = TenantUser::count();
        $activeUsers = TenantUser::where('is_active', true)->count();
        $adminUsers = TenantUser::where('role', 'admin')->count();
        $managerUsers = TenantUser::where('role', 'manager')->count();
        $regularUsers = TenantUser::where('role', 'member')->count();

        // Users created in the last 30 days
        $newUsers = TenantUser::where('created_at', '>=', now()->subDays(30))->count();

        // Users who logged in within the last 7 days
        $recentlyActiveUsers = TenantUser::whereHas('activities', function ($query) {
            $query->where('action', 'login')
                ->where('created_at', '>=', now()->subDays(7));
        })->count();

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'inactive_users' => $totalUsers - $activeUsers,
            'admin_users' => $adminUsers,
            'manager_users' => $managerUsers,
            'regular_users' => $regularUsers,
            'new_users_30d' => $newUsers,
            'recently_active_users' => $recentlyActiveUsers,
        ];
    }

    private function gatherActivityStats(): array
    {
        $totalActivities = TenantUserActivity::count();

        // Activities in the last 24 hours
        $recentActivities = TenantUserActivity::where('created_at', '>=', now()->subDay())->count();

        // Login activities in the last 7 days
        $recentLogins = TenantUserActivity::where('action', 'login')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        // Most active users (by activity count in last 30 days)
        $mostActiveUsers = TenantUserActivity::select('user_id', DB::raw('COUNT(*) as activity_count'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('user_id')
            ->orderBy('activity_count', 'desc')
            ->take(5)
            ->get();

        // Activity breakdown by action type
        $activityBreakdown = TenantUserActivity::select('action', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('action')
            ->get()
            ->pluck('count', 'action')
            ->toArray();

        return [
            'total_activities' => $totalActivities,
            'recent_activities_24h' => $recentActivities,
            'recent_logins_7d' => $recentLogins,
            'most_active_users' => $mostActiveUsers->toArray(),
            'activity_breakdown' => $activityBreakdown,
            'last_activity_at' => TenantUserActivity::latest()->first()?->created_at,
        ];
    }

    private function updateTenantManagement(Tenant $tenant, array $userStats, array $activityStats): void
    {
        TenantUserManagement::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'user_count' => $userStats['total_users'],
                'active_users' => $userStats['active_users'],
                'admin_users' => $userStats['admin_users'],
                'manager_users' => $userStats['manager_users'],
                'regular_users' => $userStats['regular_users'],
                'new_users_30d' => $userStats['new_users_30d'],
                'recently_active_users' => $userStats['recently_active_users'],
                'total_activities' => $activityStats['total_activities'],
                'recent_activities_24h' => $activityStats['recent_activities_24h'],
                'recent_logins_7d' => $activityStats['recent_logins_7d'],
                'last_activity_at' => $activityStats['last_activity_at'],
                'activity_breakdown' => $activityStats['activity_breakdown'],
                'most_active_users' => $activityStats['most_active_users'],
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'central',
            'tenant:'.$this->tenantId,
            'stats-update',
        ];
    }
}
