<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\CreateTenantUserRequest;
use App\Http\Requests\Central\UpdateTenantUserRequest;
use App\Http\Resources\Central\TenantUserActivityResource;
use App\Http\Resources\Central\TenantUserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant;
use App\Services\Central\TenantUserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantUserManagementController extends Controller
{
    public function __construct(
        private readonly TenantUserManagementService $userManagementService
    ) {}

    /**
     * List users across tenants or for a specific tenant.
     */
    public function index(Request $request): JsonResponse|View
    {
        $request->validate([
            'tenant_id' => ['nullable', 'string', 'exists:tenants,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'in:owner,admin,manager,member'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['integer', 'min:10', 'max:100'],
        ]);

        $filters = $request->only(['search', 'role', 'is_active']);
        $perPage = $request->input('per_page', 15);

        if ($tenantId = $request->input('tenant_id')) {
            $tenant = Tenant::findOrFail($tenantId);
            $users = $this->userManagementService->getTenantUsers($tenant, $filters, $perPage);
        } else {
            $users = $this->userManagementService->getAllTenantUsers($filters, $perPage);
        }

        // Handle web request vs API request
        if ($request->expectsJson()) {
            return ApiResponse::paginated(
                TenantUserResource::collection($users),
                $users,
                'Tenant users retrieved successfully'
            );
        }

        // Web interface
        $tenants = Tenant::all();
        $userCollection = $users->items();
        $pagination = [
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(),
        ];

        // Transform users to resource format for the view
        $usersForView = TenantUserResource::collection($userCollection)->toArray($request);

        return view('admin.tenant-users.index', [
            'users' => $usersForView,
            'pagination' => $pagination,
            'tenants' => $tenants,
        ]);
    }

    /**
     * Get detailed information about a specific tenant user.
     */
    public function show(string $id): JsonResponse|View
    {
        // Parse tenant and user ID - can be tenant_id:user_id or just user_id
        if (str_contains($id, ':')) {
            [$tenantId, $userId] = explode(':', $id, 2);
            $tenant = Tenant::findOrFail($tenantId);
            $user = $this->userManagementService->getTenantUser($tenant, $userId);
        } else {
            // Legacy support - find user by ID across tenants
            $userWithTenant = $this->userManagementService->findUserAcrossTenants($id);
            if (! $userWithTenant) {
                if (request()->expectsJson()) {
                    return ApiResponse::error('User not found', [], 404);
                }
                abort(404, 'User not found');
            }
            $tenant = $userWithTenant['tenant'];
            $user = $userWithTenant['user'];
        }

        if (! $user) {
            if (request()->expectsJson()) {
                return ApiResponse::error('User not found', [], 404);
            }
            abort(404, 'User not found');
        }

        if (request()->expectsJson()) {
            return ApiResponse::success(
                'Tenant user retrieved successfully',
                ['user' => new TenantUserResource($user)]
            );
        }

        // Web view
        return view('admin.tenant-users.show', compact('user', 'tenant'));
    }

    /**
     * Create a new user in a tenant.
     */
    public function store(CreateTenantUserRequest $request): JsonResponse|RedirectResponse
    {
        $tenantId = $request->input('tenant_id');

        if (! $tenantId) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Tenant ID is required', [], 422);
            }

            return back()->withErrors(['tenant_id' => 'Tenant is required']);
        }

        $tenant = Tenant::findOrFail($tenantId);

        try {
            $user = $this->userManagementService->createTenantUser(
                $tenant,
                $request->validated()
            );

            if ($request->expectsJson()) {
                return ApiResponse::created(
                    'Tenant user created successfully',
                    ['user' => new TenantUserResource($user)]
                );
            }

            return redirect()->route('admin.tenant-users.show', ['id' => $tenant->id.':'.$user->id])
                ->with('success', 'User created successfully');
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return ApiResponse::error(
                    'Failed to create tenant user',
                    ['error' => $e->getMessage()],
                    422
                );
            }

            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    /**
     * Update a tenant user.
     */
    public function update(UpdateTenantUserRequest $request, string $id): JsonResponse|RedirectResponse
    {
        // Parse tenant and user ID
        if (str_contains($id, ':')) {
            [$tenantId, $userId] = explode(':', $id, 2);
            $tenant = Tenant::findOrFail($tenantId);
            $user = $this->userManagementService->getTenantUser($tenant, $userId);
        } else {
            // Legacy support - find user by ID across tenants
            $userWithTenant = $this->userManagementService->findUserAcrossTenants($id);
            if (! $userWithTenant) {
                if ($request->expectsJson()) {
                    return ApiResponse::error('User not found', [], 404);
                }

                return back()->withErrors(['error' => 'User not found']);
            }
            $tenant = $userWithTenant['tenant'];
            $user = $userWithTenant['user'];
        }

        if (! $user) {
            if ($request->expectsJson()) {
                return ApiResponse::error('User not found', [], 404);
            }

            return back()->withErrors(['error' => 'User not found']);
        }

        try {
            $updatedUser = $this->userManagementService->updateTenantUser(
                $tenant,
                $user,
                $request->validated()
            );

            if ($request->expectsJson()) {
                return ApiResponse::success(
                    'Tenant user updated successfully',
                    ['user' => new TenantUserResource($updatedUser)]
                );
            }

            return redirect()->route('admin.tenant-users.show', ['id' => $tenant->id.':'.$updatedUser->id])
                ->with('success', 'User updated successfully');
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return ApiResponse::error(
                    'Failed to update tenant user',
                    ['error' => $e->getMessage()],
                    422
                );
            }

            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    /**
     * Delete a tenant user.
     */
    public function destroy(string $id): JsonResponse|RedirectResponse
    {
        // Parse tenant and user ID
        if (str_contains($id, ':')) {
            [$tenantId, $userId] = explode(':', $id, 2);
            $tenant = Tenant::findOrFail($tenantId);
            $user = $this->userManagementService->getTenantUser($tenant, $userId);
        } else {
            // Legacy support - find user by ID across tenants
            $userWithTenant = $this->userManagementService->findUserAcrossTenants($id);
            if (! $userWithTenant) {
                if (request()->expectsJson()) {
                    return ApiResponse::error('User not found', [], 404);
                }

                return back()->withErrors(['error' => 'User not found']);
            }
            $tenant = $userWithTenant['tenant'];
            $user = $userWithTenant['user'];
        }

        if (! $user) {
            if (request()->expectsJson()) {
                return ApiResponse::error('User not found', [], 404);
            }

            return back()->withErrors(['error' => 'User not found']);
        }

        try {
            $this->userManagementService->deleteTenantUser($tenant, $user);

            if (request()->expectsJson()) {
                return ApiResponse::success('Tenant user deleted successfully');
            }

            return redirect()->route('admin.tenant-users.index')
                ->with('success', 'User deleted successfully');
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return ApiResponse::error(
                    'Failed to delete tenant user',
                    ['error' => $e->getMessage()],
                    422
                );
            }

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Suspend a tenant user (API endpoint, not used by web routes).
     */
    public function suspend(Request $request, string $tenantId, string $userId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        $user = $this->userManagementService->getTenantUser($tenant, $userId);

        if (! $user) {
            return ApiResponse::error('User not found', [], 404);
        }

        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->userManagementService->suspendUser(
                $tenant,
                $user,
                $request->input('reason')
            );

            return ApiResponse::success(
                'Tenant user suspended successfully',
                ['user' => new TenantUserResource($user->fresh())]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to suspend tenant user',
                ['error' => $e->getMessage()],
                422
            );
        }
    }

    /**
     * Activate a tenant user (API endpoint, not used by web routes).
     */
    public function activate(Request $request, string $tenantId, string $userId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        $user = $this->userManagementService->getTenantUser($tenant, $userId);

        if (! $user) {
            return ApiResponse::error('User not found', [], 404);
        }

        try {
            $this->userManagementService->activateUser($tenant, $user);

            return ApiResponse::success(
                'Tenant user activated successfully',
                ['user' => new TenantUserResource($user->fresh())]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to activate tenant user',
                ['error' => $e->getMessage()],
                422
            );
        }
    }

    /**
     * Get user activities for a specific tenant user.
     */
    public function activities(Request $request, string $tenantId, string $userId): JsonResponse
    {
        $request->validate([
            'limit' => ['integer', 'min:10', 'max:100'],
            'days' => ['integer', 'min:1', 'max:365'],
        ]);

        $tenant = Tenant::findOrFail($tenantId);
        $user = $this->userManagementService->getTenantUser($tenant, $userId);

        if (! $user) {
            return ApiResponse::error('User not found', [], 404);
        }

        $limit = $request->input('limit', 50);
        $days = $request->input('days', 30);

        $activities = $this->userManagementService->getUserActivities(
            $tenant,
            $user,
            $limit,
            $days
        );

        return ApiResponse::success(
            'User activities retrieved successfully',
            ['activities' => TenantUserActivityResource::collection($activities)]
        );
    }

    /**
     * Get statistics for a specific tenant's users.
     */
    public function statistics(Request $request, string $tenantId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        $stats = $this->userManagementService->getTenantUserStatistics($tenant);

        return ApiResponse::success(
            'Tenant user statistics retrieved successfully',
            ['statistics' => $stats]
        );
    }

    /**
     * Show form to create a new tenant user.
     */
    public function create(Request $request): View
    {
        $request->validate([
            'tenant_id' => ['nullable', 'string', 'exists:tenants,id'],
        ]);

        $tenants = Tenant::all();
        $selectedTenant = null;

        if ($tenantId = $request->input('tenant_id')) {
            $selectedTenant = Tenant::findOrFail($tenantId);
        }

        $roles = [
            'owner' => 'Owner',
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'member' => 'Member',
        ];

        return view('admin.tenant-users.create', compact('tenants', 'selectedTenant', 'roles'));
    }

    /**
     * Show form to edit a tenant user.
     */
    public function edit(string $id): View
    {
        // Find user across all tenants - the ID should be in format tenant_id:user_id or just user_id
        if (str_contains($id, ':')) {
            [$tenantId, $userId] = explode(':', $id, 2);
        } else {
            // Legacy support - find user by ID across tenants
            $userWithTenant = $this->userManagementService->findUserAcrossTenants($id);
            if (! $userWithTenant) {
                abort(404, 'User not found');
            }
            $tenant = $userWithTenant['tenant'];
            $user = $userWithTenant['user'];
        }

        if (! isset($user)) {
            $tenant = Tenant::findOrFail($tenantId);
            $user = $this->userManagementService->getTenantUser($tenant, $userId);
            if (! $user) {
                abort(404, 'User not found');
            }
        }

        $roles = [
            'owner' => 'Owner',
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'member' => 'Member',
        ];

        return view('admin.tenant-users.edit', compact('user', 'tenant', 'roles'));
    }

    /**
     * Toggle user active/inactive status.
     */
    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        // Parse tenant and user ID
        if (str_contains($id, ':')) {
            [$tenantId, $userId] = explode(':', $id, 2);
        } else {
            // Legacy support - find user by ID across tenants
            $userWithTenant = $this->userManagementService->findUserAcrossTenants($id);
            if (! $userWithTenant) {
                return ApiResponse::error('User not found', [], 404);
            }
            $tenant = $userWithTenant['tenant'];
            $user = $userWithTenant['user'];
        }

        if (! isset($user)) {
            $tenant = Tenant::findOrFail($tenantId);
            $user = $this->userManagementService->getTenantUser($tenant, $userId);
            if (! $user) {
                return ApiResponse::error('User not found', [], 404);
            }
        }

        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $newStatus = ! $user->is_active;

            if ($newStatus) {
                $this->userManagementService->activateUser($tenant, $user);
                $message = 'User activated successfully';
            } else {
                $this->userManagementService->suspendUser(
                    $tenant,
                    $user,
                    $request->input('reason', 'Status toggled by admin')
                );
                $message = 'User suspended successfully';
            }

            return ApiResponse::success(
                $message,
                [
                    'user' => new TenantUserResource($user->fresh()),
                    'new_status' => $newStatus,
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to toggle user status',
                ['error' => $e->getMessage()],
                422
            );
        }
    }

    /**
     * Show user activity page.
     */
    public function activity(Request $request, string $id): View
    {
        // Parse tenant and user ID
        if (str_contains($id, ':')) {
            [$tenantId, $userId] = explode(':', $id, 2);
        } else {
            // Legacy support - find user by ID across tenants
            $userWithTenant = $this->userManagementService->findUserAcrossTenants($id);
            if (! $userWithTenant) {
                abort(404, 'User not found');
            }
            $tenant = $userWithTenant['tenant'];
            $user = $userWithTenant['user'];
        }

        if (! isset($user)) {
            $tenant = Tenant::findOrFail($tenantId);
            $user = $this->userManagementService->getTenantUser($tenant, $userId);
            if (! $user) {
                abort(404, 'User not found');
            }
        }

        $request->validate([
            'days' => ['integer', 'min:1', 'max:365'],
            'action_filter' => ['nullable', 'string'],
        ]);

        $days = $request->input('days', 30);
        $actionFilter = $request->input('action_filter');

        $activities = $this->userManagementService->getUserActivities(
            $tenant,
            $user,
            100, // Limit for web view
            $days,
            $actionFilter
        );

        $activityStats = $this->userManagementService->getUserActivityStats($tenant, $user, $days);

        return view('admin.tenant-users.activity', compact(
            'user',
            'tenant',
            'activities',
            'activityStats',
            'days',
            'actionFilter'
        ));
    }

    /**
     * Bulk operations on tenant users.
     */
    public function bulkAction(Request $request, string $tenantId): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'string', 'in:suspend,activate,delete'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $tenant = Tenant::findOrFail($tenantId);

        try {
            $result = $this->userManagementService->bulkAction(
                $tenant,
                $request->input('action'),
                $request->input('user_ids'),
                $request->input('reason')
            );

            return ApiResponse::success(
                "Bulk {$request->input('action')} completed successfully",
                [
                    'processed' => $result['processed'],
                    'failed' => $result['failed'],
                    'errors' => $result['errors'],
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Bulk operation failed',
                ['error' => $e->getMessage()],
                422
            );
        }
    }
}
