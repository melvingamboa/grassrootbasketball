<?php

namespace App\Http\Requests\Team;

use App\Enums\PlayerPosition;
use App\Enums\RegistrationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertPlayerRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'player_id' => [$required, 'integer', 'exists:players,id'],
            'jersey_number' => [$required, 'integer', 'between:0,99'],
            'position' => [$required, Rule::enum(PlayerPosition::class)],
            'status' => [$required, Rule::enum(RegistrationStatus::class)],
        ];
    }
}
