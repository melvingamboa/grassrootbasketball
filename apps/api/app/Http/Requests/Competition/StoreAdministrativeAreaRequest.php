<?php

namespace App\Http\Requests\Competition;

use App\Enums\AdministrativeAreaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdministrativeAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_platform_admin;
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'exists:administrative_areas,id'],
            'type' => ['required', Rule::enum(AdministrativeAreaType::class)],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32'],
        ];
    }
}
