<?php

namespace App\Http\Requests\Game;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlayerStatRequest extends FormRequest
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
            'stat' => ['required', Rule::in(['rebounds', 'assists', 'steals', 'blocks', 'turnovers', 'fouls'])],
            'delta' => ['required', 'integer', Rule::in([-1, 1])],
            'reason' => ['nullable', Rule::requiredIf(fn () => (int) $this->input('delta') < 0), 'string', 'max:500'],
        ];
    }
}
