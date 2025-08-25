<?php

namespace App\Http\Controllers\Central\Web;

use App\Http\Controllers\Traits\HandlesExceptions;
use App\Http\Controllers\Traits\HandlesTenantContext;
use App\Http\Controllers\WebController;
use App\Http\Resources\Central\TenantUserResource;
use App\Models\Tenant;
use App\Services\Central\TenantUserManagementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantUserViewController extends WebController
{
    use HandlesExceptions, HandlesTenantContext;

    public function __construct(
        private readonly TenantUserManagementService $userManagementService
    ) {}

    /**
     * List users across tenants or for a specific tenant.
     */
    public function index(Request $request): View
    {
        return $this->executeForWeb(function () use ($request) {
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

            return $this->view('admin.tenant-users.index', [
                'users' => $usersForView,
                'pagination' => $pagination,
                'tenants' => $tenants,
            ]);
        }, $request, 'listing tenant users');
    }

    /**
     * Show form to create a new tenant user.
     */
    public function create(Request $request): View
    {
        return $this->executeForWeb(function () use ($request) {
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

            return $this->view('admin.tenant-users.create', compact('tenants', 'selectedTenant', 'roles'));
        }, $request, 'showing user creation form');
    }

    /**
     * Show form to edit a tenant user.
     */
    public function edit(string $id): View
    {
        return $this->executeForWeb(function () use ($id) {
            // Find user across all tenants - the ID should be in format tenant_id:user_id or just user_id
            if (str_contains($id, ':')) {
                [$tenantId, $userId] = explode(':', $id, 2);
                $tenant = Tenant::findOrFail($tenantId);
                $user = $this->userManagementService->getTenantUser($tenant, $userId);
            } else {
                // Legacy support - find user by ID across tenants
                $userWithTenant = $this->userManagementService->findUserAcrossTenants($id);
                if (! $userWithTenant) {
                    abort(404, 'User not found');
                }
                $tenant = $userWithTenant['tenant'];
                $user = $userWithTenant['user'];
            }

            if (! $user) {
                abort(404, 'User not found');
            }

            $roles = [
                'owner' => 'Owner',
                'admin' => 'Administrator',
                'manager' => 'Manager',
                'member' => 'Member',
            ];

            return $this->view('admin.tenant-users.edit', compact('user', 'tenant', 'roles'));
        }, request(), 'showing user edit form');
    }

    /**
     * Show user activity page.
     */
    public function activity(Request $request, string $id): View
    {
        return $this->executeForWeb(function () use ($request, $id) {
            // Parse tenant and user ID
            if (str_contains($id, ':')) {
                [$tenantId, $userId] = explode(':', $id, 2);
                $tenant = Tenant::findOrFail($tenantId);
                $user = $this->userManagementService->getTenantUser($tenant, $userId);
            } else {
                // Legacy support - find user by ID across tenants
                $userWithTenant = $this->userManagementService->findUserAcrossTenants($id);
                if (! $userWithTenant) {
                    abort(404, 'User not found');
                }
                $tenant = $userWithTenant['tenant'];
                $user = $userWithTenant['user'];
            }

            if (! $user) {
                abort(404, 'User not found');
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

            return $this->view('admin.tenant-users.activity', compact(
                'user',
                'tenant',
                'activities',
                'activityStats',
                'days',
                'actionFilter'
            ));
        }, $request, 'showing user activity');
    }

    /**
     * Show user detail page.
     */
    public function show(string $id): View
    {
        return $this->executeForWeb(function () use ($id) {
            // Parse tenant and user ID - can be tenant_id:user_id or just user_id
            if (str_contains($id, ':')) {
                [$tenantId, $userId] = explode(':', $id, 2);
                $tenant = Tenant::findOrFail($tenantId);
                $user = $this->userManagementService->getTenantUser($tenant, $userId);
            } else {
                // Legacy support - find user by ID across tenants
                $userWithTenant = $this->userManagementService->findUserAcrossTenants($id);
                if (! $userWithTenant) {
                    abort(404, 'User not found');
                }
                $tenant = $userWithTenant['tenant'];
                $user = $userWithTenant['user'];
            }

            if (! $user) {
                abort(404, 'User not found');
            }

            return $this->view('admin.tenant-users.show', compact('user', 'tenant'));
        }, request(), 'showing user details');
    }

    /**
     * Show tenant users dashboard.
     */
    public function dashboard(Request $request): View
    {
        return $this->executeForWeb(function () use ($request) {
            $request->validate([
                'tenant_id' => ['nullable', 'string', 'exists:tenants,id'],
            ]);

            $tenants = Tenant::all();
            $selectedTenant = null;
            $stats = [];

            if ($tenantId = $request->input('tenant_id')) {
                $selectedTenant = Tenant::findOrFail($tenantId);
                $stats = $this->userManagementService->getTenantUserStatistics($selectedTenant);
            } else {
                // Global stats across all tenants
                $stats = $this->userManagementService->getGlobalUserStatistics();
            }

            return $this->view('admin.tenant-users.dashboard', compact('tenants', 'selectedTenant', 'stats'));
        }, $request, 'showing users dashboard');
    }
}
