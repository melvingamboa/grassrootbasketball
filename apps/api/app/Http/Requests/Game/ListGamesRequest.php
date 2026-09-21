<?php

namespace App\Http\Requests\Game;

use App\Enums\GameStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListGamesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'team_registration_id' => ['sometimes', 'integer'],
            'status' => ['sometimes', Rule::enum(GameStatus::class)],
        ];
    }
}
