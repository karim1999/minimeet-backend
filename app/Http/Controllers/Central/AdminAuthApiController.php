<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Traits\HandlesExceptions;
use App\Http\Requests\Central\AdminLoginRequest;
use App\Http\Resources\Central\CentralUserResource;
use App\Services\Central\AdminAuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuthApiController extends ApiController
{
    use HandlesExceptions;

    public function __construct(
        private readonly AdminAuthenticationService $authService
    ) {}

    /**
     * Handle admin login request (API only).
     */
    public function login(AdminLoginRequest $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $result = $this->authService->authenticate($request->validated());

            return $this->respondWithSuccess([
                'user' => new CentralUserResource($result['user']),
                'token' => $result['token'],
                'permissions' => $this->getUserPermissions($result['user']),
            ], 'Admin authenticated successfully');
        }, 'authenticating admin user');
    }

    /**
     * Handle admin logout request (API only).
     */
    public function logout(Request $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $this->authService->logout($request);

            return $this->respondWithSuccess(
                null,
                'Admin logged out successfully'
            );
        }, 'logging out admin user');
    }

    /**
     * Get authenticated admin user information.
     */
    public function user(Request $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $user = $this->authService->getCurrentUser($request);

            if (! $user) {
                return $this->respondUnauthorized('User not authenticated');
            }

            return $this->respondWithSuccess([
                'user' => new CentralUserResource($user),
                'permissions' => $this->getUserPermissions($user),
                'session_expires_at' => $this->authService->getSessionExpiresAt(),
            ], 'User retrieved successfully');
        }, 'retrieving current admin user');
    }

    /**
     * Refresh authentication token.
     */
    public function refresh(Request $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $result = $this->authService->refreshToken($request);

            return $this->respondWithSuccess([
                'token' => $result['token'],
                'expires_at' => $result['expires_at'],
            ], 'Token refreshed successfully');
        }, 'refreshing authentication token');
    }

    /**
     * Verify two-factor authentication.
     */
    public function verifyTwoFactor(Request $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $request->validate([
                'code' => ['required', 'string', 'size:6'],
                'remember' => ['boolean'],
            ]);

            $result = $this->authService->verifyTwoFactor(
                $request->input('code'),
                $request->boolean('remember', false)
            );

            return $this->respondWithSuccess([
                'user' => new CentralUserResource($result['user']),
                'token' => $result['token'],
                'permissions' => $this->getUserPermissions($result['user']),
            ], 'Two-factor authentication verified successfully');
        }, 'verifying two-factor authentication');
    }

    /**
     * Get user permissions.
     */
    public function permissions(Request $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $user = $this->authService->getCurrentUser($request);

            if (! $user) {
                return $this->respondUnauthorized('User not authenticated');
            }

            $permissions = $this->getUserPermissions($user);

            return $this->respondWithSuccess([
                'permissions' => $permissions,
                'role' => $user->role,
            ], 'User permissions retrieved successfully');
        }, 'retrieving user permissions');
    }

    /**
     * Get user permissions based on role.
     */
    private function getUserPermissions($user): array
    {
        return match ($user->role) {
            'super_admin' => [
                'manage_tenants',
                'manage_users',
                'view_system_stats',
                'manage_settings',
                'view_audit_logs',
                'manage_billing',
            ],
            'admin' => [
                'manage_tenants',
                'manage_users',
                'view_stats',
            ],
            default => [],
        };
    }
}
