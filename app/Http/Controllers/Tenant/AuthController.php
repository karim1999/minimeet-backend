<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Traits\HandlesExceptions;
use App\Http\Requests\Tenant\ForgotPasswordRequest;
use App\Http\Requests\Tenant\LoginRequest;
use App\Http\Requests\Tenant\RegisterRequest;
use App\Http\Requests\Tenant\ResetPasswordRequest;
use App\Http\Resources\Tenant\TenantUserResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\TenantAuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends ApiController
{
    use HandlesExceptions;

    public function __construct(
        private readonly TenantAuthenticationService $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $result = $this->authService->register(
                $request->validated(),
                tenant('id'),
                $request->ip(),
                $request->userAgent() ?? ''
            );

            return $this->respondCreated([
                'user' => new TenantUserResource($result['user']),
                'token' => $result['token']->plainTextToken,
            ], 'User registered successfully');
        }, 'registering tenant user');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $result = $this->authService->authenticate(
                $request->only(['email', 'password']),
                tenant('id'),
                $request->ip(),
                $request->userAgent() ?? ''
            );

            return $this->respondWithSuccess([
                'user' => new TenantUserResource($result['user']),
                'token' => $result['token']->plainTextToken,
            ], 'Login successful');
        }, 'authenticating tenant user');
    }

    public function logout(Request $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $user = $request->user();

            if (! $user) {
                return $this->respondUnauthorized('User not authenticated');
            }

            // Directly revoke the current token used for this request
            $currentToken = $user->currentAccessToken();
            if ($currentToken) {
                $currentToken->delete();
            }

            // Also revoke all user tokens to ensure complete logout
            $user->tokens()->delete();

            $this->authService->logout($user, tenant('id'));

            return $this->respondWithSuccess(
                null,
                'Logged out successfully'
            );
        }, 'logging out tenant user');
    }

    public function user(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return ApiResponse::error('User not authenticated', 401);
            }

            return ApiResponse::success(
                'User retrieved successfully',
                [
                    'user' => new TenantUserResource($user),
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve user: '.$e->getMessage(),
                500
            );
        }
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->sendPasswordResetLink(
                $request->validated('email'),
                tenant('id')
            );

            // For invalid emails, we still return success to avoid user enumeration
            // but for tests that expect 422 for truly invalid email formats,
            // we should validate first
            return ApiResponse::success(
                'If this email exists in our system, a password reset link has been sent.'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error(
                'Validation failed',
                $e->errors(),
                422
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to send password reset link: '.$e->getMessage(),
                500
            );
        }
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->resetPassword(
                $request->validated(),
                tenant('id')
            );

            if (! $result['success']) {
                return ApiResponse::error($result['message'], 400);
            }

            return ApiResponse::success(
                'Password reset successful',
                [
                    'user' => new TenantUserResource($result['user']),
                    'token' => $result['token']->plainTextToken,
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Password reset failed: '.$e->getMessage(),
                500
            );
        }
    }
}
