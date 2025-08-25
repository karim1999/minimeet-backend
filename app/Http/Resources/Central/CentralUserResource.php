<?php

namespace App\Http\Resources\Central;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CentralUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'role_display' => $this->getRoleDisplayName(),
            'is_central' => $this->is_central,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'metadata' => $this->metadata,
            'permissions' => [
                'is_super_admin' => $this->isSuperAdmin(),
                'is_admin' => $this->isAdmin(),
                'can_manage_tenants' => $this->isSuperAdmin(),
                'can_view_statistics' => $this->isAdmin(),
            ],
        ];
    }
}
