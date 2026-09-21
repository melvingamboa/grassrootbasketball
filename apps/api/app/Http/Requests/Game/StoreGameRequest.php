<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class StoreGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'division_id' => ['required', 'integer', 'exists:divisions,id'],
            'home_team_registration_id' => ['required', 'integer', 'exists:season_team_registrations,id', 'different:away_team_registration_id'],
            'away_team_registration_id' => ['required', 'integer', 'exists:season_team_registrations,id', 'different:home_team_registration_id'],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'scheduled_at' => ['required', 'date'],
            'estimated_duration_minutes' => ['required', 'integer', 'between:30,240'],
            'round' => ['nullable', 'string', 'max:64'],
            'allow_conflicts' => ['sometimes', 'boolean'],
        ];
    }
}
