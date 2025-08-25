<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\AdminLoginRequest;
use App\Http\Resources\Central\CentralUserResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\AdminAuthenticationService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function __construct(
        private readonly AdminAuthenticationService $authService
    ) {}

    /**
     * Show admin login form.
     */
    public function showLogin(): View|RedirectResponse
    {
        if ($this->authService->isSessionValid()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    /**
     * Handle admin login request.
     */
    public function login(AdminLoginRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $result = $this->authService->authenticate($request->validated());

            // Handle web request vs API request
            if ($request->expectsJson()) {
                return ApiResponse::success(
                    'Admin authenticated successfully',
                    [
                        'user' => new CentralUserResource($result['user']),
                        'token' => $result['token'],
                        'permissions' => $this->getUserPermissions($result['user']),
                    ]
                );
            }

            return redirect()->route('admin.dashboard');
        } catch (AuthenticationException $e) {
            if ($request->expectsJson()) {
                return ApiResponse::error(
                    $e->getMessage(),
                    ['email' => [$e->getMessage()]],
                    401,
                    'ADMIN_AUTH_FAILED'
                );
            }

            return back()->withErrors(['email' => $e->getMessage()]);
        }
    }

    /**
     * Handle admin logout request.
     */
    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        $this->authService->invalidateSession();

        if ($request->expectsJson()) {
            return ApiResponse::success('Admin logged out successfully');
        }

        return redirect()->route('admin.login');
    }

    /**
     * Get current authenticated admin user.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $this->authService->getCurrentUser();

        if (! $user) {
            return ApiResponse::error('Unauthenticated', [], 401);
        }

        return ApiResponse::success(
            'Admin user retrieved successfully',
            [
                'user' => new CentralUserResource($user),
                'permissions' => $this->getUserPermissions($user),
            ]
        );
    }

    /**
     * Refresh authentication session.
     */
    public function refresh(Request $request): JsonResponse
    {
        if (! $this->authService->isSessionValid()) {
            return ApiResponse::error(
                'Session expired',
                [],
                401,
                'SESSION_EXPIRED'
            );
        }

        $this->authService->refreshSession();
        $user = $this->authService->getCurrentUser();

        return ApiResponse::success(
            'Session refreshed successfully',
            [
                'user' => new CentralUserResource($user),
                'permissions' => $this->getUserPermissions($user),
            ]
        );
    }

    /**
     * Verify two-factor authentication code.
     */
    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $this->authService->getCurrentUser();

        if (! $user) {
            return ApiResponse::error('User not found', [], 404);
        }

        $isValid = $this->authService->verifyTwoFactor($user, $request->code);

        if (! $isValid) {
            return ApiResponse::error(
                'Invalid two-factor authentication code',
                ['code' => ['The provided code is invalid.']],
                422,
                'INVALID_2FA_CODE'
            );
        }

        // Mark 2FA as verified in session
        session(['2fa_verified' => true]);

        return ApiResponse::success(
            'Two-factor authentication verified',
            ['verified' => true]
        );
    }

    /**
     * Check admin permissions.
     */
    public function permissions(Request $request): JsonResponse
    {
        $user = $this->authService->getCurrentUser();

        if (! $user) {
            return ApiResponse::error('Unauthenticated', [], 401);
        }

        return ApiResponse::success(
            'Permissions retrieved successfully',
            ['permissions' => $this->getUserPermissions($user)]
        );
    }

    /**
     * Get user permissions array.
     *
     * @param  \App\Models\Central\CentralUser  $user
     */
    private function getUserPermissions($user): array
    {
        return [
            'role' => $user->role,
            'role_display' => $user->getRoleDisplayName(),
            'is_super_admin' => $user->isSuperAdmin(),
            'is_admin' => $user->isAdmin(),
            'permissions' => [
                'create_tenant' => $this->authService->canPerformAction('create_tenant'),
                'delete_tenant' => $this->authService->canPerformAction('delete_tenant'),
                'manage_users' => $this->authService->canPerformAction('manage_users'),
                'view_statistics' => $this->authService->canPerformAction('view_statistics'),
            ],
        ];
    }
}
