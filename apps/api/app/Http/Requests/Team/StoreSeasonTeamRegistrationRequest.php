<?php

namespace App\Http\Requests\Team;

use App\Enums\RegistrationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSeasonTeamRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'division_id' => ['required', 'integer', 'exists:divisions,id'],
            'status' => ['required', Rule::enum(RegistrationStatus::class)],
        ];
    }
}
