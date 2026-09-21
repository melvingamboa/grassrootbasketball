<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationMembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'joined_at' => $this->joined_at?->toIso8601String(),
            'user' => new UserResource($this->whenLoaded('user')),
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
        ];
    }
}
