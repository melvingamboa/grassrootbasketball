<?php

namespace App\Http\Requests\Game;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGameLineupRequest extends FormRequest
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
            'home_player_registration_ids' => ['present', 'array', 'max:20'],
            'home_player_registration_ids.*' => ['integer', 'distinct'],
            'away_player_registration_ids' => ['present', 'array', 'max:20'],
            'away_player_registration_ids.*' => ['integer', 'distinct'],
            'home_starter_player_registration_ids' => ['sometimes', 'array', 'size:5'],
            'home_starter_player_registration_ids.*' => ['integer', 'distinct'],
            'away_starter_player_registration_ids' => ['sometimes', 'array', 'size:5'],
            'away_starter_player_registration_ids.*' => ['integer', 'distinct'],
        ];
    }
}
