<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\TenantUser;
use App\Models\Tenant\TenantUserActivity;
use Illuminate\Support\Facades\Hash;

class TenantUserManagementService
{
    public function createUser(array $userData): TenantUser
    {
        // Hash password if provided
        if (isset($userData['password'])) {
            $userData['password'] = Hash::make($userData['password']);
        }

        $user = TenantUser::create($userData);

        // Log user creation
        TenantUserActivity::create([
            'user_id' => $user->id,
            'action' => 'user_created',
            'description' => "User {$user->name} was created",
            'ip_address' => request()->ip(),
            'metadata' => [
                'created_by' => auth('tenant_sanctum')->id(),
                'role' => $user->role,
            ],
        ]);

        return $user->fresh();
    }

    public function updateUser(TenantUser $user, array $userData): TenantUser
    {
        $originalData = $user->toArray();

        // Hash password if provided
        if (isset($userData['password'])) {
            $userData['password'] = Hash::make($userData['password']);
        }

        $user->update($userData);

        // Log user update
        $changes = [];
        foreach ($userData as $key => $value) {
            if ($key !== 'password' && ($originalData[$key] ?? null) !== $value) {
                $changes[$key] = [
                    'old' => $originalData[$key] ?? null,
                    'new' => $value,
                ];
            }
        }

        if (isset($userData['password'])) {
            $changes['password'] = 'changed';
        }

        if (! empty($changes)) {
            TenantUserActivity::create([
                'user_id' => $user->id,
                'action' => 'user_updated',
                'description' => "User {$user->name} was updated",
                'ip_address' => request()->ip(),
                'metadata' => [
                    'updated_by' => auth('tenant_sanctum')->id(),
                    'changes' => $changes,
                ],
            ]);
        }

        return $user->fresh();
    }

    public function deleteUser(TenantUser $user): void
    {
        // Log user deletion
        TenantUserActivity::create([
            'user_id' => $user->id,
            'action' => 'user_deleted',
            'description' => "User {$user->name} was deleted",
            'ip_address' => request()->ip(),
            'metadata' => [
                'deleted_by' => auth('tenant_sanctum')->id(),
                'user_data' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
            ],
        ]);

        $user->delete();
    }

    public function toggleUserStatus(TenantUser $user): TenantUser
    {
        $newStatus = ! $user->is_active;
        $user->update(['is_active' => $newStatus]);

        // Log status change
        TenantUserActivity::create([
            'user_id' => $user->id,
            'action' => $newStatus ? 'user_activated' : 'user_deactivated',
            'description' => "User {$user->name} was ".($newStatus ? 'activated' : 'deactivated'),
            'ip_address' => request()->ip(),
            'metadata' => [
                'changed_by' => auth('tenant_sanctum')->id(),
                'status' => $newStatus,
            ],
        ]);

        return $user->fresh();
    }

    public function resetUserPassword(TenantUser $user, string $newPassword): TenantUser
    {
        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        // Log password reset
        TenantUserActivity::create([
            'user_id' => $user->id,
            'action' => 'password_reset',
            'description' => "Password was reset for user {$user->name}",
            'ip_address' => request()->ip(),
            'metadata' => [
                'reset_by' => auth('tenant_sanctum')->id(),
            ],
        ]);

        return $user->fresh();
    }

    public function getUserStats(TenantUser $user): array
    {
        $totalActivities = TenantUserActivity::where('user_id', $user->id)->count();
        $recentActivities = TenantUserActivity::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return [
            'total_activities' => $totalActivities,
            'recent_activities' => $recentActivities,
            'last_login' => $user->last_login_at?->toISOString(),
            'account_age_days' => $user->created_at?->diffInDays(now()) ?? 0,
            'is_active' => $user->is_active,
        ];
    }
}
