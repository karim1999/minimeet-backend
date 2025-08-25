<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'bio' => $this->bio,
            'avatar_url' => $this->avatar_url,
            'email_verified_at' => $this->when(
                $this->email_verified_at,
                fn () => $this->email_verified_at?->toISOString()
            ),
            'last_login_at' => $this->when(
                $this->last_login_at,
                fn () => $this->last_login_at?->toISOString()
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'permissions' => $this->when(
                $this->relationLoaded('permissions'),
                fn () => $this->permissions->pluck('name')
            ),
        ];
    }
}
