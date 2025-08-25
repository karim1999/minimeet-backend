<?php

namespace App\Http\Resources\Central;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantUserActivityResource extends JsonResource
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
            'user_id' => $this->user_id,
            'action' => $this->action,
            'formatted_action' => $this->formatted_action,
            'description' => $this->description,
            'display_description' => $this->display_description,
            'model_type' => $this->model_type,
            'model_id' => $this->model_id,
            'metadata' => $this->metadata,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at->toISOString(),
            'time_ago' => $this->time_ago,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'role' => $this->user->role,
            ],
            'summary' => $this->summary,
        ];
    }
}
