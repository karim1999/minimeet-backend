<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Traits\HandlesExceptions;
use App\Http\Requests\Tenant\ChangePasswordRequest;
use App\Http\Requests\Tenant\UpdateProfileRequest;
use App\Http\Resources\Tenant\TenantUserResource;
use App\Services\Tenant\TenantUserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends ApiController
{
    use HandlesExceptions;

    public function __construct(
        private readonly TenantUserManagementService $userService
    ) {}

    /**
     * Get authenticated user's profile.
     */
    public function profile(Request $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $user = $request->user('tenant_sanctum');

            return $this->respondWithSuccess([
                'user' => new TenantUserResource($user),
            ], 'Profile retrieved successfully');
        }, 'retrieving user profile');
    }

    /**
     * Update authenticated user's profile.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $user = $request->user('tenant_sanctum');
            $updatedUser = $this->userService->updateUser($user, $request->validated());

            return $this->respondWithSuccess([
                'user' => new TenantUserResource($updatedUser),
            ], 'Profile updated successfully');
        }, 'updating user profile');
    }

    /**
     * Change authenticated user's password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $user = $request->user('tenant_sanctum');
            $validated = $request->validated();

            // Verify current password
            if (! Hash::check($validated['current_password'], $user->password)) {
                return $this->respondWithError('Current password is incorrect', 400);
            }

            // Update password directly using the service
            $user->update([
                'password' => Hash::make($validated['password']),
            ]);

            // Log the password change activity
            $user->activities()->create([
                'action' => 'password_changed',
                'description' => 'User changed their password',
                'ip_address' => $request->ip(),
            ]);

            return $this->respondWithSuccess(
                null,
                'Password changed successfully'
            );
        }, 'changing user password');
    }

    /**
     * Get user preferences.
     */
    public function preferences(Request $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $user = $request->user('tenant_sanctum');
            $preferences = $this->userService->getUserPreferences($user);

            return $this->respondWithSuccess(
                $preferences,
                'User preferences retrieved successfully'
            );
        }, 'retrieving user preferences');
    }

    /**
     * Update user preferences.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $request->validate([
                'notifications' => ['nullable', 'array'],
                'notifications.email' => ['boolean'],
                'notifications.push' => ['boolean'],
                'notifications.sms' => ['boolean'],
                'theme' => ['nullable', 'string', 'in:light,dark,auto'],
                'language' => ['nullable', 'string', 'size:2'],
                'timezone' => ['nullable', 'string'],
            ]);

            $user = $request->user('tenant_sanctum');
            $preferences = $this->userService->updateUserPreferences($user, $request->validated());

            return $this->respondWithSuccess(
                $preferences,
                'User preferences updated successfully'
            );
        }, 'updating user preferences');
    }

    /**
     * Upload profile avatar.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $request->validate([
                'avatar' => ['required', 'image', 'max:2048'], // 2MB max
            ]);

            $user = $request->user('tenant_sanctum');
            $avatarPath = $this->userService->uploadAvatar($user, $request->file('avatar'));

            return $this->respondWithSuccess([
                'avatar_url' => $avatarPath,
            ], 'Avatar uploaded successfully');
        }, 'uploading user avatar');
    }

    /**
     * Delete profile avatar.
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        return $this->executeForApi(function () use ($request) {
            $user = $request->user('tenant_sanctum');
            $this->userService->deleteAvatar($user);

            return $this->respondWithSuccess(
                null,
                'Avatar deleted successfully'
            );
        }, 'deleting user avatar');
    }
}
