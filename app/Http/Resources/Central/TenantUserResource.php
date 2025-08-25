<?php

namespace App\Http\Resources\Central;

use App\Models\Central\TenantUserManagement;
use App\Models\Tenant\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Handle TenantUserManagement models (central stats)
        if ($this->resource instanceof TenantUserManagement) {
            return $this->transformTenantUserManagement();
        }

        // Handle TenantUser models (actual tenant users)
        if ($this->resource instanceof TenantUser) {
            return $this->transformTenantUser();
        }

        // Fallback for any other type
        return $this->transformGenericUser();
    }

    /**
     * Transform TenantUserManagement model to user-like structure.
     */
    private function transformTenantUserManagement(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->tenant?->name ?? 'Unknown Tenant',
            'email' => 'tenant-stats@' . ($this->tenant?->domains->first()?->domain ?? 'unknown.com'),
            'role' => 'tenant_stats',
            'role_display' => 'Tenant Statistics',
            'department' => null,
            'title' => 'Statistics Record',
            'phone' => null,
            'avatar_url' => null,
            'initials' => substr($this->tenant?->name ?? 'TS', 0, 2),
            'full_display_name' => ($this->tenant?->name ?? 'Unknown') . ' (Statistics)',
            'is_active' => $this->hasRecentActivity(),
            'last_login_at' => $this->last_activity_at?->toISOString(),
            'email_verified_at' => null,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'deleted_at' => null,
            'tenant' => $this->tenant ? [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
                'domains' => $this->tenant->domains->pluck('domain'),
            ] : null,
            'user_count' => $this->user_count,
            'active_users' => $this->active_users,
            'permissions' => [
                'is_owner' => false,
                'is_admin' => false,
                'is_manager' => false,
                'can_manage_users' => false,
            ],
            'activity_status' => [
                'has_recent_activity' => $this->hasRecentActivity(),
                'last_activity_days' => $this->last_activity_at ?
                    $this->last_activity_at->diffInDays(now()) : null,
                'activity_status' => $this->getActivityStatus(),
            ],
            'settings' => [],
            'metadata' => $this->metadata ?? [],
        ];
    }

    /**
     * Transform TenantUser model.
     */
    private function transformTenantUser(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'role_display' => $this->getRoleDisplayName(),
            'department' => $this->department,
            'title' => $this->title,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'initials' => $this->initials,
            'full_display_name' => $this->full_display_name,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'tenant' => [
                'id' => tenant('id'),
                'name' => tenant('name'),
            ],
            'permissions' => [
                'is_owner' => $this->isOwner(),
                'is_admin' => $this->isAdmin(),
                'is_manager' => $this->isManager(),
                'can_manage_users' => $this->canManageUsers(),
            ],
            'activity_status' => [
                'has_recent_activity' => $this->hasRecentActivity(),
                'last_activity_days' => $this->last_login_at ?
                    $this->last_login_at->diffInDays(now()) : null,
            ],
            'settings' => $this->settings ?? [],
            'metadata' => $this->metadata ?? [],
        ];
    }

    /**
     * Fallback transformation for generic user-like objects.
     */
    private function transformGenericUser(): array
    {
        return [
            'id' => $this->id ?? null,
            'name' => $this->name ?? 'Unknown User',
            'email' => $this->email ?? 'unknown@example.com',
            'role' => $this->role ?? 'unknown',
            'role_display' => 'Unknown Role',
            'department' => $this->department ?? null,
            'title' => $this->title ?? null,
            'phone' => $this->phone ?? null,
            'avatar_url' => $this->avatar_url ?? null,
            'initials' => 'UK',
            'full_display_name' => $this->name ?? 'Unknown User',
            'is_active' => $this->is_active ?? false,
            'last_login_at' => $this->last_login_at?->toISOString() ?? null,
            'email_verified_at' => null,
            'created_at' => $this->created_at?->toISOString() ?? now()->toISOString(),
            'updated_at' => $this->updated_at?->toISOString() ?? now()->toISOString(),
            'deleted_at' => null,
            'tenant' => null,
            'permissions' => [
                'is_owner' => false,
                'is_admin' => false,
                'is_manager' => false,
                'can_manage_users' => false,
            ],
            'activity_status' => [
                'has_recent_activity' => false,
                'last_activity_days' => null,
            ],
            'settings' => [],
            'metadata' => [],
        ];
    }
}
