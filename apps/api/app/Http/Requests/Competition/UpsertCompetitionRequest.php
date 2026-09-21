<?php

namespace App\Http\Requests\Competition;

use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertCompetitionRequest extends FormRequest
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
            'administrative_area_id' => ['nullable', 'integer', 'exists:administrative_areas,id'],
            'type' => [$required, Rule::enum(CompetitionType::class)],
            'status' => [$required, Rule::enum(CompetitionStatus::class)],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
