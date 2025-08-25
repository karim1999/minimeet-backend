<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ChangePasswordRequest;
use App\Http\Requests\Tenant\CreateUserRequest;
use App\Http\Requests\Tenant\UpdateProfileRequest;
use App\Http\Requests\Tenant\UpdateUserRequest;
use App\Http\Resources\Tenant\TenantUserActivityResource;
use App\Http\Resources\Tenant\TenantUserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\TenantUser;
use App\Models\Tenant\TenantUserActivity;
use App\Services\Tenant\TenantUserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(
        private readonly TenantUserManagementService $userService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min((int) $request->get('per_page', 15), 100);
            $search = $request->get('search');
            $role = $request->get('role');
            $status = $request->get('status');

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

            return ApiResponse::success(
                'Users retrieved successfully',
                [
                    'users' => TenantUserResource::collection($users->items()),
                    'pagination' => [
                        'current_page' => $users->currentPage(),
                        'last_page' => $users->lastPage(),
                        'per_page' => $users->perPage(),
                        'total' => $users->total(),
                    ],
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve users: '.$e->getMessage(),
                500
            );
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $user = TenantUser::findOrFail($id);

            return ApiResponse::success(
                'User retrieved successfully',
                ['user' => new TenantUserResource($user)]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'User not found: '.$e->getMessage(),
                404
            );
        }
    }

    public function store(CreateUserRequest $request): JsonResponse
    {
        try {
            $userData = $request->validated();
            $userData['tenant_id'] = tenant('id');

            $user = $this->userService->createUser($userData);

            return ApiResponse::success(
                'User created successfully',
                ['user' => new TenantUserResource($user)],
                201
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to create user: '.$e->getMessage(),
                500
            );
        }
    }

    public function update(string $id, UpdateUserRequest $request): JsonResponse
    {
        try {
            $user = TenantUser::findOrFail($id);

            $user = $this->userService->updateUser($user, $request->validated());

            return ApiResponse::success(
                'User updated successfully',
                ['user' => new TenantUserResource($user)]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to update user: '.$e->getMessage(),
                500
            );
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $user = TenantUser::findOrFail($id);

            // Cannot delete self
            if ($user->id === auth('tenant_sanctum')->id()) {
                return ApiResponse::error('Cannot delete your own account', 400);
            }

            $this->userService->deleteUser($user);

            return ApiResponse::success(
                'User deleted successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to delete user: '.$e->getMessage(),
                500
            );
        }
    }

    public function toggleStatus(string $id): JsonResponse
    {
        try {
            $user = TenantUser::findOrFail($id);

            // Cannot deactivate self
            if ($user->id === auth('tenant_sanctum')->id()) {
                return ApiResponse::error('Cannot deactivate your own account', 400);
            }

            $user = $this->userService->toggleUserStatus($user);

            return ApiResponse::success(
                'User status updated successfully',
                ['user' => new TenantUserResource($user)]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to toggle user status: '.$e->getMessage(),
                500
            );
        }
    }

    public function profile(Request $request): JsonResponse
    {
        try {
            $user = $request->user('tenant_sanctum');

            return ApiResponse::success(
                'Profile retrieved successfully',
                ['user' => new TenantUserResource($user)]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve profile: '.$e->getMessage(),
                500
            );
        }
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $request->user('tenant_sanctum');

            $user = $this->userService->updateUser($user, $request->validated());

            return ApiResponse::success(
                'Profile updated successfully',
                ['user' => new TenantUserResource($user)]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to update profile: '.$e->getMessage(),
                500
            );
        }
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $user = $request->user('tenant_sanctum');

            // Verify current password
            if (! Hash::check($request->validated('current_password'), $user->password)) {
                return ApiResponse::error('Current password is incorrect', 400);
            }

            $user->update([
                'password' => Hash::make($request->validated('password')),
            ]);

            // Log the password change
            TenantUserActivity::create([
                'user_id' => $user->id,
                'action' => 'password_changed',
                'description' => 'User changed their password',
                'ip_address' => $request->ip(),
            ]);

            return ApiResponse::success(
                'Password changed successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to change password: '.$e->getMessage(),
                500
            );
        }
    }

    public function activity(string $id, Request $request): JsonResponse
    {
        try {
            $user = TenantUser::findOrFail($id);
            $perPage = min((int) $request->get('per_page', 15), 100);

            $activities = TenantUserActivity::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return ApiResponse::success(
                'User activities retrieved successfully',
                [
                    'activities' => TenantUserActivityResource::collection($activities->items()),
                    'pagination' => [
                        'current_page' => $activities->currentPage(),
                        'last_page' => $activities->lastPage(),
                        'per_page' => $activities->perPage(),
                        'total' => $activities->total(),
                    ],
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve user activity: '.$e->getMessage(),
                500
            );
        }
    }
}
