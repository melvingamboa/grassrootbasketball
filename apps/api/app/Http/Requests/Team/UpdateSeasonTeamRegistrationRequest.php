<?php

namespace App\Http\Requests\Team;

use App\Enums\RegistrationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSeasonTeamRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'division_id' => ['sometimes', 'integer', 'exists:divisions,id'],
            'status' => ['sometimes', Rule::enum(RegistrationStatus::class)],
        ];
    }
}
