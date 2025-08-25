<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Traits\HandlesApiPagination;
use App\Http\Controllers\Traits\HandlesExceptions;
use App\Http\Requests\Tenant\CreateUserRequest;
use App\Http\Requests\Tenant\UpdateUserRequest;
use App\Http\Resources\Tenant\TenantUserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\TenantUser;
use App\Services\Tenant\TenantUserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantUserController extends ApiController
{
    use HandlesApiPagination, HandlesExceptions;

    public function __construct(
        private readonly TenantUserManagementService $userService
    ) {}

    /**
     * List tenant users with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $request->validate([
                'per_page' => ['integer', 'min:5', 'max:100'],
                'search' => ['nullable', 'string', 'max:255'],
                'role' => ['nullable', 'string', 'in:owner,admin,manager,member'],
                'status' => ['nullable', 'boolean'],
            ]);

            $perPage = $request->input('per_page', 15);
            $search = $request->input('search');
            $role = $request->input('role');
            $status = $request->input('status');

            $users = TenantUser::query()
                ->when($search, function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->when($role, function ($query, $role) {
                    $query->where('role', $role);
                })
                ->when($status !== null, function ($query) use ($status) {
                    $query->where('is_active', (bool) $status);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return $this->respondWithSuccess([
                'users' => TenantUserResource::collection($users->items()),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
            ], 'Users retrieved successfully');
        }, 'listing tenant users');
    }

    /**
     * Get a specific tenant user.
     */
    public function show(string $id): JsonResponse
    {
        return $this->executeForApi(function () use ($id) {
            $user = TenantUser::findOrFail($id);

            return $this->respondWithSuccess([
                'user' => new TenantUserResource($user),
            ], 'User retrieved successfully');
        }, 'showing tenant user');
    }

    /**
     * Create a new tenant user.
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $user = $this->userService->createUser($request->validated());

            return $this->respondCreated([
                'user' => new TenantUserResource($user),
            ], 'User created successfully');
        }, 'creating tenant user');
    }

    /**
     * Update a tenant user.
     */
    public function update(string $id, UpdateUserRequest $request): JsonResponse
    {
        return $this->executeForApi(function () use ($id, $request) {
            $user = TenantUser::findOrFail($id);
            $updatedUser = $this->userService->updateUser($user, $request->validated());

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
            $user = TenantUser::findOrFail($id);

            // Prevent self-deletion
            if ($user->id === auth()->id()) {
                return $this->respondWithError('You cannot delete your own account', 400);
            }

            $this->userService->deleteUser($user);

            return $this->respondWithSuccess(
                null,
                'User deleted successfully'
            );
        }, 'deleting tenant user');
    }

    /**
     * Toggle user active status.
     */
    public function toggleStatus(string $id): JsonResponse
    {
        return $this->executeForApi(function () use ($id) {
            $user = TenantUser::findOrFail($id);

            // Prevent self-status change
            if ($user->id === auth()->id()) {
                return $this->respondWithError('You cannot change your own status', 400);
            }

            $updatedUser = $this->userService->toggleUserStatus($user);

            return $this->respondWithSuccess(
                new TenantUserResource($updatedUser),
                $updatedUser->is_active ? 'User activated successfully' : 'User deactivated successfully'
            );
        }, 'toggling tenant user status');
    }
}
