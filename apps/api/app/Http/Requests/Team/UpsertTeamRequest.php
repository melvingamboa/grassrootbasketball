<?php

namespace App\Http\Requests\Team;

use App\Enums\TeamStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:32'],
            'administrative_area_id' => ['nullable', 'integer', 'exists:administrative_areas,id'],
            'primary_color' => [$required, 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => [$required, 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => [$required, Rule::enum(TeamStatus::class)],
        ];
    }
}
