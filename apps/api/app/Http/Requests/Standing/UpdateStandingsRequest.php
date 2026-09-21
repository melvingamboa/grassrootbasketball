<?php

namespace App\Http\Requests\Standing;

use App\Enums\StandingQualificationStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStandingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'division_id' => ['required', 'integer', 'exists:divisions,id'],
            'rows' => ['present', 'array', 'max:128'],
            'rows.*.team_registration_id' => ['required', 'integer', 'distinct', 'exists:season_team_registrations,id'],
            'rows.*.qualification_status' => ['required', Rule::enum(StandingQualificationStatus::class)],
            'rows.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
