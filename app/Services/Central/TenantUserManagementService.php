<?php

namespace App\Services\Central;

use App\Models\Central\TenantUserManagement;
use App\Models\Tenant;
use App\Models\Tenant\TenantUser;
use App\Models\Tenant\TenantUserActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TenantUserManagementService
{
    /**
     * Get paginated users for a specific tenant.
     */
    public function getTenantUsers(Tenant $tenant, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        tenancy()->initialize($tenant);

        try {
            $query = TenantUser::query();

            if (! empty($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            }

            if (! empty($filters['role'])) {
                $query->where('role', $filters['role']);
            }

            if (isset($filters['is_active'])) {
                $query->where('is_active', $filters['is_active']);
            }

            return $query->orderBy('created_at', 'desc')->paginate($perPage);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Get all tenant users across all tenants (for super admin view).
     */
    public function getAllTenantUsers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        // This is a complex operation that requires querying across all tenant databases
        // For now, we'll return a simple collection based on tenant user management stats
        $tenantManagement = TenantUserManagement::with('tenant')
            ->paginate($perPage);

        return $tenantManagement;
    }

    /**
     * Get a specific tenant user.
     */
    public function getTenantUser(Tenant $tenant, string $userId): ?TenantUser
    {
        tenancy()->initialize($tenant);

        try {
            return TenantUser::find($userId);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Create a new user in a tenant.
     */
    public function createTenantUser(Tenant $tenant, array $data): TenantUser
    {
        tenancy()->initialize($tenant);

        try {
            // Check if email already exists in this tenant
            if (TenantUser::where('email', $data['email'])->exists()) {
                throw new \Exception('Email already exists in this tenant.');
            }

            $user = TenantUser::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $data['role'] ?? 'member',
                'department' => $data['department'] ?? null,
                'title' => $data['title'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // Log the creation
            $user->logActivity('user_created_by_admin', null, [
                'created_by' => 'central_admin',
            ], 'User account created by system administrator');

            return $user;
        } finally {
            tenancy()->end();
            // Update tenant user stats
            $this->updateTenantUserStats($tenant);
        }
    }

    /**
     * Update a tenant user.
     */
    public function updateTenantUser(Tenant $tenant, TenantUser $user, array $data): TenantUser
    {
        tenancy()->initialize($tenant);

        try {
            // Check email uniqueness if email is being changed
            if (isset($data['email']) && $data['email'] !== $user->email) {
                if (TenantUser::where('email', $data['email'])->where('id', '!=', $user->id)->exists()) {
                    throw new \Exception('Email already exists in this tenant.');
                }
            }

            $originalData = $user->toArray();

            $user->update(array_filter([
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'role' => $data['role'] ?? null,
                'department' => $data['department'] ?? null,
                'title' => $data['title'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'] ?? null,
            ], fn ($value) => $value !== null));

            // Update password if provided
            if (! empty($data['password'])) {
                $user->update(['password' => $data['password']]);
            }

            // Log the update
            $user->logActivity('user_updated_by_admin', null, [
                'updated_by' => 'central_admin',
                'changes' => array_diff_assoc($user->toArray(), $originalData),
            ], 'User account updated by system administrator');

            return $user->fresh();
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Delete a tenant user.
     */
    public function deleteTenantUser(Tenant $tenant, TenantUser $user): bool
    {
        tenancy()->initialize($tenant);

        try {
            // Prevent deletion of the last owner
            if ($user->isOwner() && TenantUser::where('role', 'owner')->count() <= 1) {
                throw new \Exception('Cannot delete the last owner of the tenant.');
            }

            // Log before deletion
            $user->logActivity('user_deleted_by_admin', null, [
                'deleted_by' => 'central_admin',
            ], 'User account deleted by system administrator');

            return $user->delete();
        } finally {
            tenancy()->end();
            // Update tenant user stats
            $this->updateTenantUserStats($tenant);
        }
    }

    /**
     * Suspend a tenant user.
     */
    public function suspendUser(Tenant $tenant, TenantUser $user, ?string $reason = null): bool
    {
        tenancy()->initialize($tenant);

        try {
            $user->update(['is_active' => false]);

            // Revoke all tokens
            $user->tokens()->delete();

            // Log suspension
            $user->logActivity('user_suspended_by_admin', null, [
                'suspended_by' => 'central_admin',
                'reason' => $reason,
            ], 'User account suspended by system administrator');

            return true;
        } finally {
            tenancy()->end();
            // Update tenant user stats
            $this->updateTenantUserStats($tenant);
        }
    }

    /**
     * Activate a tenant user.
     */
    public function activateUser(Tenant $tenant, TenantUser $user): bool
    {
        tenancy()->initialize($tenant);

        try {
            $user->update(['is_active' => true]);

            // Log activation
            $user->logActivity('user_activated_by_admin', null, [
                'activated_by' => 'central_admin',
            ], 'User account activated by system administrator');

            return true;
        } finally {
            tenancy()->end();
            // Update tenant user stats
            $this->updateTenantUserStats($tenant);
        }
    }

    /**
     * Get user activities for a specific tenant user.
     */
    public function getUserActivities(Tenant $tenant, TenantUser $user, int $limit = 50, int $days = 30): Collection
    {
        tenancy()->initialize($tenant);

        try {
            return TenantUserActivity::where('user_id', $user->id)
                ->where('created_at', '>=', now()->subDays($days))
                ->latest()
                ->take($limit)
                ->get();
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Get statistics for a specific tenant's users.
     */
    public function getTenantUserStatistics(Tenant $tenant): array
    {
        tenancy()->initialize($tenant);

        try {
            $totalUsers = TenantUser::count();
            $activeUsers = TenantUser::where('is_active', true)->count();
            $usersByRole = TenantUser::selectRaw('role, COUNT(*) as count')
                ->groupBy('role')
                ->pluck('count', 'role')
                ->toArray();

            $recentLogins = TenantUser::whereNotNull('last_login_at')
                ->where('last_login_at', '>=', now()->subDays(7))
                ->count();

            $newUsersThisMonth = TenantUser::where('created_at', '>=', now()->startOfMonth())
                ->count();

            return [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'inactive_users' => $totalUsers - $activeUsers,
                'users_by_role' => $usersByRole,
                'recent_logins_7_days' => $recentLogins,
                'new_users_this_month' => $newUsersThisMonth,
                'activity_rate' => $totalUsers > 0 ? ($recentLogins / $totalUsers) * 100 : 0,
            ];
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Perform bulk actions on tenant users.
     */
    public function bulkAction(Tenant $tenant, string $action, array $userIds, ?string $reason = null): array
    {
        $processed = 0;
        $failed = 0;
        $errors = [];

        tenancy()->initialize($tenant);

        try {
            foreach ($userIds as $userId) {
                try {
                    $user = TenantUser::find($userId);

                    if (! $user) {
                        $errors[] = "User ID {$userId} not found";
                        $failed++;

                        continue;
                    }

                    match ($action) {
                        'suspend' => $this->suspendUser($tenant, $user, $reason),
                        'activate' => $this->activateUser($tenant, $user),
                        'delete' => $this->deleteTenantUser($tenant, $user),
                    };

                    $processed++;
                } catch (\Exception $e) {
                    $errors[] = "User ID {$userId}: ".$e->getMessage();
                    $failed++;
                }
            }

            return [
                'processed' => $processed,
                'failed' => $failed,
                'errors' => $errors,
            ];
        } finally {
            tenancy()->end();
            // Update tenant user stats
            $this->updateTenantUserStats($tenant);
        }
    }

    /**
     * Update tenant user statistics.
     */
    public function updateTenantUserStats(Tenant $tenant): void
    {
        $userManagement = TenantUserManagement::firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'user_count' => 0,
                'active_users' => 0,
                'last_activity_at' => null,
                'metadata' => [],
            ]
        );

        $userManagement->updateStats();
    }

    /**
     * Search users across all tenants (Super Admin only).
     */
    public function searchUsersAcrossTenants(string $query): array
    {
        $results = [];
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            try {
                $users = TenantUser::where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%");
                })->take(10)->get();

                if ($users->isNotEmpty()) {
                    $results[] = [
                        'tenant_id' => $tenant->id,
                        'tenant_domains' => $tenant->domains->pluck('domain'),
                        'users' => $users->toArray(),
                    ];
                }
            } finally {
                tenancy()->end();
            }
        }

        return $results;
    }
}
