<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Traits\HandlesApiPagination;
use App\Http\Controllers\Traits\HandlesExceptions;
use App\Http\Controllers\Traits\HandlesTenantContext;
use App\Http\Resources\Central\TenantUserActivityResource;
use App\Models\Tenant;
use App\Services\Central\TenantUserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantUserActivityController extends ApiController
{
    use HandlesApiPagination, HandlesExceptions, HandlesTenantContext;

    public function __construct(
        private readonly TenantUserManagementService $userManagementService
    ) {}

    /**
     * Get activities for a specific tenant user.
     */
    public function activities(Request $request, string $tenantId, string $userId): JsonResponse
    {
        return $this->executeForApi(function () use ($request, $tenantId, $userId) {
            $request->validate([
                'limit' => ['integer', 'min:10', 'max:100'],
                'days' => ['integer', 'min:1', 'max:365'],
                'action_filter' => ['nullable', 'string'],
            ]);

            $tenant = Tenant::findOrFail($tenantId);
            $user = $this->userManagementService->getTenantUser($tenant, $userId);

            if (! $user) {
                return $this->respondNotFound('User not found');
            }

            $limit = $request->input('limit', 50);
            $days = $request->input('days', 30);
            $actionFilter = $request->input('action_filter');

            $activities = $this->userManagementService->getUserActivities(
                $tenant,
                $user,
                $limit,
                $days,
                $actionFilter
            );

            return $this->respondWithSuccess([
                'activities' => TenantUserActivityResource::collection($activities),
            ], 'User activities retrieved successfully');
        }, 'retrieving user activities');
    }

    /**
     * Get activity statistics for a specific tenant user.
     */
    public function activityStats(Request $request, string $tenantId, string $userId): JsonResponse
    {
        return $this->executeForApi(function () use ($request, $tenantId, $userId) {
            $request->validate([
                'days' => ['integer', 'min:1', 'max:365'],
            ]);

            $tenant = Tenant::findOrFail($tenantId);
            $user = $this->userManagementService->getTenantUser($tenant, $userId);

            if (! $user) {
                return $this->respondNotFound('User not found');
            }

            $days = $request->input('days', 30);

            $activityStats = $this->userManagementService->getUserActivityStats($tenant, $user, $days);

            return $this->respondWithSuccess(
                $activityStats,
                'User activity statistics retrieved successfully'
            );
        }, 'retrieving user activity statistics');
    }

    /**
     * Get statistics for all users in a specific tenant.
     */
    public function statistics(Request $request, string $tenantId): JsonResponse
    {
        return $this->executeForApi(function () use ($tenantId) {
            $tenant = Tenant::findOrFail($tenantId);
            $stats = $this->userManagementService->getTenantUserStatistics($tenant);

            return $this->respondWithSuccess(
                $stats,
                'Tenant user statistics retrieved successfully'
            );
        }, 'retrieving tenant user statistics');
    }

    /**
     * Get activity summary for a tenant.
     */
    public function tenantActivitySummary(Request $request, string $tenantId): JsonResponse
    {
        return $this->executeForApi(function () use ($request, $tenantId) {
            $request->validate([
                'days' => ['integer', 'min:1', 'max:365'],
                'group_by' => ['nullable', 'string', 'in:day,week,month'],
            ]);

            $tenant = Tenant::findOrFail($tenantId);
            $days = $request->input('days', 30);
            $groupBy = $request->input('group_by', 'day');

            $activitySummary = $this->userManagementService->getTenantActivitySummary($tenant, $days, $groupBy);

            return $this->respondWithSuccess(
                $activitySummary,
                'Tenant activity summary retrieved successfully'
            );
        }, 'retrieving tenant activity summary');
    }

    /**
     * Get recent activity for dashboard.
     */
    public function recentActivity(Request $request, string $tenantId): JsonResponse
    {
        return $this->executeForApi(function () use ($request, $tenantId) {
            $request->validate([
                'limit' => ['integer', 'min:5', 'max:50'],
            ]);

            $tenant = Tenant::findOrFail($tenantId);
            $limit = $request->input('limit', 10);

            $recentActivities = $this->userManagementService->getRecentActivities($tenant, $limit);

            return $this->respondWithSuccess([
                'activities' => TenantUserActivityResource::collection($recentActivities),
            ], 'Recent activities retrieved successfully');
        }, 'retrieving recent activities');
    }
}
