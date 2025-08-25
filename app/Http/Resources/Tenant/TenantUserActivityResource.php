<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantUserActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'description' => $this->description,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'user' => $this->when(
                $this->relationLoaded('tenantUser'),
                fn () => new TenantUserResource($this->tenantUser)
            ),
        ];
    }
}
