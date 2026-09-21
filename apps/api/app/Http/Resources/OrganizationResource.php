<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'role' => $this->current_role,
            'member_count' => $this->whenCounted('memberships'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
