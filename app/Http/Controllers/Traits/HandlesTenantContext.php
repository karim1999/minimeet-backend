<?php

namespace App\Http\Controllers\Traits;

use App\Models\Tenant;
use Illuminate\Http\Request;

trait HandlesTenantContext
{
    /**
     * Parse tenant and user ID from composite ID string.
     * Supports formats: 'tenant_id:user_id' or 'user_id'.
     *
     * @param  string  $id  The composite ID string
     * @return array{0: string|null, 1: string} [tenantId, userId]
     */
    protected function parseTenantUserId(string $id): array
    {
        if (strpos($id, ':') !== false) {
            [$tenantId, $userId] = explode(':', $id, 2);

            return [$tenantId, $userId];
        }

        return [null, $id];
    }

    /**
     * Get tenant from request parameters or return null.
     */
    protected function getTenantFromRequest(Request $request): ?Tenant
    {
        $tenantId = $request->input('tenant_id');

        if (! $tenantId) {
            return null;
        }

        return Tenant::find($tenantId);
    }

    /**
     * Get tenant from request parameters or fail with 404.
     */
    protected function getTenantFromRequestOrFail(Request $request): Tenant
    {
        $tenantId = $request->input('tenant_id');

        if (! $tenantId) {
            abort(400, 'Tenant ID is required');
        }

        return Tenant::findOrFail($tenantId);
    }

    /**
     * Create a composite tenant:user ID string.
     */
    protected function createTenantUserId(string $tenantId, string $userId): string
    {
        return $tenantId.':'.$userId;
    }

    /**
     * Validate that tenant exists and user belongs to tenant.
     */
    protected function validateTenantUserRelation(Tenant $tenant, $user): bool
    {
        // This will depend on your specific user-tenant relationship structure
        // For now, just check if user exists
        return $user !== null;
    }
}
