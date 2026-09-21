<?php

namespace App\Http\Requests\Bracket;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBracketMatchRequest extends FormRequest
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
            'round_number' => ['required', 'integer', 'min:1', 'max:32'],
            'match_number' => ['required', 'integer', 'min:1', 'max:128'],
            'round_label' => ['required', 'string', 'max:64'],
            'home_team_registration_id' => ['nullable', 'integer', 'exists:season_team_registrations,id', 'different:away_team_registration_id'],
            'away_team_registration_id' => ['nullable', 'integer', 'exists:season_team_registrations,id', 'different:home_team_registration_id'],
            'winner_team_registration_id' => ['nullable', 'integer', 'exists:season_team_registrations,id'],
            'game_id' => ['nullable', 'integer', 'exists:games,id'],
        ];
    }
}
