<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdministrativeAreaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'name' => $this->name,
            'code' => $this->code,
            'path' => $this->path ?? $this->name,
        ];
    }
}
