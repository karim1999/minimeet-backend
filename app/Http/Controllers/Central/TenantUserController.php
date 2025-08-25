<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Traits\HandlesApiPagination;
use App\Http\Controllers\Traits\HandlesExceptions;
use App\Http\Controllers\Traits\HandlesTenantContext;
use App\Http\Requests\Central\CreateTenantUserRequest;
use App\Http\Requests\Central\UpdateTenantUserRequest;
use App\Http\Resources\Central\TenantUserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant;
use App\Services\Central\TenantUserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantUserController extends ApiController
{
    use HandlesApiPagination, HandlesExceptions, HandlesTenantContext;

    public function __construct(
        private readonly TenantUserManagementService $userManagementService
    ) {}

    /**
     * List users across tenants or for a specific tenant.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
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

            return ApiResponse::paginated(
                TenantUserResource::collection($users),
                $users,
                'Tenant users retrieved successfully'
            );
        }, 'listing tenant users');
    }

    /**
     * Get detailed information about a specific tenant user.
     */
    public function show(string $id): JsonResponse
    {
        return $this->executeForApi(function () use ($id) {
            // Parse tenant and user ID - can be tenant_id:user_id or just user_id
            if (str_contains($id, ':')) {
                [$tenantId, $userId] = explode(':', $id, 2);
                $tenant = Tenant::findOrFail($tenantId);
                $user = $this->userManagementService->getTenantUser($tenant, $userId);
            } else {
                // Legacy support - find user by ID across tenants
                $userWithTenant = $this->userManagementService->findUserAcrossTenants($id);
                if (! $userWithTenant) {
                    return $this->respondNotFound('User not found');
                }
                $tenant = $userWithTenant['tenant'];
                $user = $userWithTenant['user'];
            }

            if (! $user) {
                return $this->respondNotFound('User not found');
            }

            return $this->respondWithSuccess(
                new TenantUserResource($user),
                'User retrieved successfully'
            );
        }, 'showing tenant user');
    }

    /**
     * Create a new tenant user.
     */
    public function store(CreateTenantUserRequest $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $validated = $request->validated();
            $tenant = Tenant::findOrFail($validated['tenant_id']);

            $user = $this->userManagementService->createUser($tenant, $validated);

            return $this->respondCreated(
                new TenantUserResource($user),
                'User created successfully'
            );
        }, 'creating tenant user');
    }

    /**
     * Update a tenant user.
     */
    public function update(UpdateTenantUserRequest $request, string $id): JsonResponse
    {
        return $this->executeForApi(function () use ($request, $id) {
            [$tenantId, $userId] = $this->parseTenantUserId($id);

            if (! $tenantId && ! $request->has('tenant_id')) {
                return $this->respondWithError('Tenant ID is required', 400);
            }

            $tenant = $tenantId
                ? Tenant::findOrFail($tenantId)
                : Tenant::findOrFail($request->input('tenant_id'));

            $user = $this->userManagementService->getTenantUser($tenant, $userId);

            if (! $user) {
                return $this->respondNotFound('User not found');
            }

            $validated = $request->validated();
            $updatedUser = $this->userManagementService->updateUser($tenant, $user, $validated);

            return $this->respondWithSuccess(
                new TenantUserResource($updatedUser),
                'User updated successfully'
            );
        }, 'updating tenant user');
    }

    /**
     * Delete a tenant user.
     */
    public function destroy(string $id): JsonResponse
    {
        return $this->executeForApi(function () use ($id) {
            [$tenantId, $userId] = $this->parseTenantUserId($id);

            if (! $tenantId) {
                return $this->respondWithError('Tenant ID is required in format tenant_id:user_id', 400);
            }

            $tenant = Tenant::findOrFail($tenantId);
            $user = $this->userManagementService->getTenantUser($tenant, $userId);

            if (! $user) {
                return $this->respondNotFound('User not found');
            }

            $this->userManagementService->deleteUser($tenant, $user);

            return $this->respondWithSuccess(
                null,
                'User deleted successfully'
            );
        }, 'deleting tenant user');
    }

    /**
     * Suspend a tenant user.
     */
    public function suspend(Request $request, string $tenantId, string $userId): JsonResponse
    {
        return $this->executeForApi(function () use ($tenantId, $userId) {
            $tenant = Tenant::findOrFail($tenantId);
            $user = $this->userManagementService->getTenantUser($tenant, $userId);

            if (! $user) {
                return $this->respondNotFound('User not found');
            }

            $updatedUser = $this->userManagementService->suspendUser($tenant, $user);

            return $this->respondWithSuccess(
                new TenantUserResource($updatedUser),
                'User suspended successfully'
            );
        }, 'suspending tenant user');
    }

    /**
     * Activate a tenant user.
     */
    public function activate(Request $request, string $tenantId, string $userId): JsonResponse
    {
        return $this->executeForApi(function () use ($tenantId, $userId) {
            $tenant = Tenant::findOrFail($tenantId);
            $user = $this->userManagementService->getTenantUser($tenant, $userId);

            if (! $user) {
                return $this->respondNotFound('User not found');
            }

            $updatedUser = $this->userManagementService->activateUser($tenant, $user);

            return $this->respondWithSuccess(
                new TenantUserResource($updatedUser),
                'User activated successfully'
            );
        }, 'activating tenant user');
    }

    /**
     * Toggle user status.
     */
    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        return $this->executeForApi(function () use ($request, $id) {
            $request->validate([
                'tenant_id' => ['required', 'string', 'exists:tenants,id'],
                'is_active' => ['required', 'boolean'],
            ]);

            $tenant = Tenant::findOrFail($request->input('tenant_id'));
            [, $userId] = $this->parseTenantUserId($id);

            $user = $this->userManagementService->getTenantUser($tenant, $userId);

            if (! $user) {
                return $this->respondNotFound('User not found');
            }

            $isActive = $request->input('is_active');
            $updatedUser = $isActive
                ? $this->userManagementService->activateUser($tenant, $user)
                : $this->userManagementService->suspendUser($tenant, $user);

            return $this->respondWithSuccess(
                new TenantUserResource($updatedUser),
                $isActive ? 'User activated successfully' : 'User suspended successfully'
            );
        }, 'toggling tenant user status');
    }
}
