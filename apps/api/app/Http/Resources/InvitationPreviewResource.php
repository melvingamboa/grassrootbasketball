<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationPreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'expires_at' => $this->expires_at->toIso8601String(),
            'organization' => [
                'name' => $this->organization->name,
                'slug' => $this->organization->slug,
            ],
        ];
    }
}
