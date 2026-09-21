<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LiveGameActionRequest extends FormRequest
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
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['start', 'score', 'correct', 'clock_start', 'clock_pause', 'clock_adjust', 'next_period', 'finalize'])],
            'team' => ['nullable', Rule::requiredIf(fn () => in_array($this->input('action'), ['score', 'correct'], true)), Rule::in(['home', 'away'])],
            'points' => ['nullable', Rule::requiredIf(fn () => in_array($this->input('action'), ['score', 'correct'], true)), 'integer', Rule::in([1, 2, 3])],
            'player_registration_id' => ['nullable', 'integer'],
            'clock_seconds' => ['nullable', Rule::requiredIf(fn () => $this->input('action') === 'clock_adjust'), 'integer', 'min:0', 'max:3600'],
            'reason' => ['nullable', Rule::requiredIf(fn () => in_array($this->input('action'), ['correct', 'clock_adjust'], true)), 'string', 'max:500'],
        ];
    }
}
