<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Traits\HandlesApiPagination;
use App\Http\Controllers\Traits\HandlesExceptions;
use App\Http\Resources\Tenant\TenantUserActivityResource;
use App\Models\Tenant\TenantUser;
use App\Models\Tenant\TenantUserActivity;
use App\Services\Tenant\TenantUserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserActivityController extends ApiController
{
    use HandlesApiPagination, HandlesExceptions;

    public function __construct(
        private readonly TenantUserManagementService $userService
    ) {}

    /**
     * Get user activities.
     */
    public function activity(string $id, Request $request): JsonResponse
    {
        return $this->executeForApi(function () use ($id, $request) {
            $request->validate([
                'limit' => ['integer', 'min:5', 'max:100'],
                'action' => ['nullable', 'string'],
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            ]);

            $user = TenantUser::findOrFail($id);
            $limit = $request->input('limit', 20);

            $activities = TenantUserActivity::where('user_id', $user->id)
                ->when($request->input('action'), function ($query, $action) {
                    $query->where('action', $action);
                })
                ->when($request->input('date_from'), function ($query, $dateFrom) {
                    $query->where('created_at', '>=', $dateFrom);
                })
                ->when($request->input('date_to'), function ($query, $dateTo) {
                    $query->where('created_at', '<=', $dateTo.' 23:59:59');
                })
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return $this->respondWithSuccess([
                'activities' => TenantUserActivityResource::collection($activities),
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $limit,
                    'total' => $activities->count(),
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ], 'User activities retrieved successfully');
        }, 'retrieving user activities');
    }

    /**
     * Get current user's activities.
     */
    public function myActivity(Request $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $request->validate([
                'per_page' => ['integer', 'min:5', 'max:100'],
                'action' => ['nullable', 'string'],
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            ]);

            $user = $request->user('tenant_sanctum');
            $perPage = $request->input('per_page', 20);

            $activities = TenantUserActivity::where('user_id', $user->id)
                ->when($request->input('action'), function ($query, $action) {
                    $query->where('action', $action);
                })
                ->when($request->input('date_from'), function ($query, $dateFrom) {
                    $query->where('created_at', '>=', $dateFrom);
                })
                ->when($request->input('date_to'), function ($query, $dateTo) {
                    $query->where('created_at', '<=', $dateTo.' 23:59:59');
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return $this->respondWithSuccess([
                'activities' => TenantUserActivityResource::collection($activities->items()),
                'pagination' => [
                    'current_page' => $activities->currentPage(),
                    'last_page' => $activities->lastPage(),
                    'per_page' => $activities->perPage(),
                    'total' => $activities->total(),
                ],
            ], 'My activities retrieved successfully');
        }, 'retrieving current user activities');
    }

    /**
     * Get user activity statistics.
     */
    public function activityStats(string $id, Request $request): JsonResponse
    {
        return $this->executeForApi(function () use ($id, $request) {
            $request->validate([
                'days' => ['integer', 'min:1', 'max:365'],
            ]);

            $user = TenantUser::findOrFail($id);
            $days = $request->input('days', 30);

            $stats = $this->userService->getUserActivityStats($user, $days);

            return $this->respondWithSuccess(
                $stats,
                'User activity statistics retrieved successfully'
            );
        }, 'retrieving user activity statistics');
    }

    /**
     * Get tenant-wide activity summary.
     */
    public function tenantActivity(Request $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $request->validate([
                'per_page' => ['integer', 'min:5', 'max:100'],
                'action' => ['nullable', 'string'],
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            ]);

            $perPage = $request->input('per_page', 50);

            $activities = TenantUserActivity::query()
                ->with('user:id,name,email')
                ->when($request->input('action'), function ($query, $action) {
                    $query->where('action', $action);
                })
                ->when($request->input('date_from'), function ($query, $dateFrom) {
                    $query->where('created_at', '>=', $dateFrom);
                })
                ->when($request->input('date_to'), function ($query, $dateTo) {
                    $query->where('created_at', '<=', $dateTo.' 23:59:59');
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return $this->respondWithSuccess([
                'activities' => TenantUserActivityResource::collection($activities->items()),
                'pagination' => [
                    'current_page' => $activities->currentPage(),
                    'last_page' => $activities->lastPage(),
                    'per_page' => $activities->perPage(),
                    'total' => $activities->total(),
                ],
            ], 'Tenant activities retrieved successfully');
        }, 'retrieving tenant activities');
    }

    /**
     * Get activity types for filtering.
     */
    public function activityTypes(Request $request): JsonResponse
    {
        return $this->executeForApi(function () {
            $types = TenantUserActivity::select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action');

            return $this->respondWithSuccess([
                'activity_types' => $types,
            ], 'Activity types retrieved successfully');
        }, 'retrieving activity types');
    }

    /**
     * Log custom activity.
     */
    public function logActivity(Request $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $request->validate([
                'action' => ['required', 'string', 'max:100'],
                'description' => ['nullable', 'string', 'max:500'],
                'metadata' => ['nullable', 'array'],
            ]);

            $user = $request->user('tenant_sanctum');
            $activity = $this->userService->logActivity(
                $user,
                $request->input('action'),
                $request->input('description'),
                $request->input('metadata', [])
            );

            return $this->respondCreated(
                new TenantUserActivityResource($activity),
                'Activity logged successfully'
            );
        }, 'logging user activity');
    }
}
