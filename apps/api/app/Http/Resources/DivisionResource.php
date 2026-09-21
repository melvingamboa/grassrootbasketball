<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DivisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug,
            'category' => $this->category->value, 'category_label' => $this->category->label(),
            'gender' => $this->gender, 'minimum_age' => $this->minimum_age, 'maximum_age' => $this->maximum_age,
            'is_active' => $this->is_active,
        ];
    }
}
