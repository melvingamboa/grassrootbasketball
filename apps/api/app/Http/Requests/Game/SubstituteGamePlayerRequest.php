<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubstituteGamePlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'team' => ['required', Rule::in(['home', 'away'])],
            'player_out_registration_id' => ['required', 'integer', 'different:player_in_registration_id'],
            'player_in_registration_id' => ['required', 'integer', 'different:player_out_registration_id'],
        ];
    }
}
