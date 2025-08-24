<?php

namespace App\Http\Resources\Central;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
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
            'company_name' => $this->data['company_name'] ?? null,
            'owner_id' => $this->data['owner_id'] ?? null,
            'domain' => $this->whenLoaded('domains', function () {
                return $this->domains->first()?->domain;
            }),
            'domains' => $this->whenLoaded('domains', function () {
                return $this->domains->pluck('domain')->toArray();
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'links' => [
                'self' => route('api.v1.tenant', ['tenant' => $this->id]),
            ],
        ];
    }
}
