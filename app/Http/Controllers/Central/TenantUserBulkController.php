<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Traits\HandlesExceptions;
use App\Http\Controllers\Traits\HandlesTenantContext;
use App\Models\Tenant;
use App\Services\Central\TenantUserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantUserBulkController extends ApiController
{
    use HandlesExceptions, HandlesTenantContext;

    public function __construct(
        private readonly TenantUserManagementService $userManagementService
    ) {}

    /**
     * Perform bulk action on tenant users.
     */
    public function bulkAction(Request $request, string $tenantId): JsonResponse
    {
        return $this->executeForApi(function () use ($request, $tenantId) {
            $request->validate([
                'action' => ['required', 'string', 'in:suspend,activate,delete'],
                'user_ids' => ['required', 'array', 'min:1'],
                'user_ids.*' => ['integer'],
                'reason' => ['nullable', 'string', 'max:500'],
            ]);

            $tenant = Tenant::findOrFail($tenantId);

            $result = $this->userManagementService->bulkAction(
                $tenant,
                $request->input('action'),
                $request->input('user_ids'),
                $request->input('reason')
            );

            return $this->respondWithSuccess([
                'processed' => $result['processed'],
                'failed' => $result['failed'],
                'errors' => $result['errors'],
            ], "Bulk {$request->input('action')} completed successfully");
        }, 'performing bulk user action');
    }

    /**
     * Bulk suspend users.
     */
    public function bulkSuspend(Request $request, string $tenantId): JsonResponse
    {
        return $this->executeForApi(function () use ($request, $tenantId) {
            $request->validate([
                'user_ids' => ['required', 'array', 'min:1'],
                'user_ids.*' => ['integer'],
                'reason' => ['nullable', 'string', 'max:500'],
            ]);

            $tenant = Tenant::findOrFail($tenantId);

            $result = $this->userManagementService->bulkSuspendUsers(
                $tenant,
                $request->input('user_ids'),
                $request->input('reason')
            );

            return $this->respondWithSuccess([
                'suspended' => $result['suspended'],
                'failed' => $result['failed'],
                'errors' => $result['errors'],
            ], 'Bulk suspend completed successfully');
        }, 'bulk suspending users');
    }

    /**
     * Bulk activate users.
     */
    public function bulkActivate(Request $request, string $tenantId): JsonResponse
    {
        return $this->executeForApi(function () use ($request, $tenantId) {
            $request->validate([
                'user_ids' => ['required', 'array', 'min:1'],
                'user_ids.*' => ['integer'],
                'reason' => ['nullable', 'string', 'max:500'],
            ]);

            $tenant = Tenant::findOrFail($tenantId);

            $result = $this->userManagementService->bulkActivateUsers(
                $tenant,
                $request->input('user_ids'),
                $request->input('reason')
            );

            return $this->respondWithSuccess([
                'activated' => $result['activated'],
                'failed' => $result['failed'],
                'errors' => $result['errors'],
            ], 'Bulk activate completed successfully');
        }, 'bulk activating users');
    }

    /**
     * Bulk delete users.
     */
    public function bulkDelete(Request $request, string $tenantId): JsonResponse
    {
        return $this->executeForApi(function () use ($request, $tenantId) {
            $request->validate([
                'user_ids' => ['required', 'array', 'min:1'],
                'user_ids.*' => ['integer'],
                'reason' => ['nullable', 'string', 'max:500'],
                'force' => ['boolean'], // for permanent deletion
            ]);

            $tenant = Tenant::findOrFail($tenantId);

            $result = $this->userManagementService->bulkDeleteUsers(
                $tenant,
                $request->input('user_ids'),
                $request->input('reason'),
                $request->boolean('force', false)
            );

            return $this->respondWithSuccess([
                'deleted' => $result['deleted'],
                'failed' => $result['failed'],
                'errors' => $result['errors'],
            ], 'Bulk delete completed successfully');
        }, 'bulk deleting users');
    }

    /**
     * Bulk update user roles.
     */
    public function bulkUpdateRoles(Request $request, string $tenantId): JsonResponse
    {
        return $this->executeForApi(function () use ($request, $tenantId) {
            $request->validate([
                'user_ids' => ['required', 'array', 'min:1'],
                'user_ids.*' => ['integer'],
                'role' => ['required', 'string', 'in:owner,admin,manager,member'],
                'reason' => ['nullable', 'string', 'max:500'],
            ]);

            $tenant = Tenant::findOrFail($tenantId);

            $result = $this->userManagementService->bulkUpdateUserRoles(
                $tenant,
                $request->input('user_ids'),
                $request->input('role'),
                $request->input('reason')
            );

            return $this->respondWithSuccess([
                'updated' => $result['updated'],
                'failed' => $result['failed'],
                'errors' => $result['errors'],
            ], 'Bulk role update completed successfully');
        }, 'bulk updating user roles');
    }

    /**
     * Get bulk operation status.
     */
    public function bulkOperationStatus(Request $request, string $operationId): JsonResponse
    {
        return $this->executeForApi(function () use ($operationId) {
            $status = $this->userManagementService->getBulkOperationStatus($operationId);

            if (! $status) {
                return $this->respondNotFound('Bulk operation not found');
            }

            return $this->respondWithSuccess(
                $status,
                'Bulk operation status retrieved successfully'
            );
        }, 'retrieving bulk operation status');
    }
}
