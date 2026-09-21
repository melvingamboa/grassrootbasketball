<?php

namespace App\Http\Requests\Game;

use App\Enums\GameStatus;
use App\Rules\SupportedLivestreamUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'division_id' => ['sometimes', 'integer', 'exists:divisions,id'],
            'home_team_registration_id' => ['sometimes', 'integer', 'exists:season_team_registrations,id', 'different:away_team_registration_id'],
            'away_team_registration_id' => ['sometimes', 'integer', 'exists:season_team_registrations,id', 'different:home_team_registration_id'],
            'venue_id' => ['sometimes', 'nullable', 'integer', 'exists:venues,id'],
            'scheduled_at' => ['sometimes', 'date'],
            'estimated_duration_minutes' => ['sometimes', 'integer', 'between:30,240'],
            'round' => ['sometimes', 'nullable', 'string', 'max:64'],
            'status' => ['sometimes', Rule::enum(GameStatus::class)],
            'home_score' => ['sometimes', 'integer', 'between:0,999'],
            'away_score' => ['sometimes', 'integer', 'between:0,999'],
            'status_reason' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'livestream_url' => ['sometimes', 'nullable', 'string', 'max:2048', new SupportedLivestreamUrl],
            'livestream_status' => ['sometimes', Rule::in(['unavailable', 'scheduled', 'live', 'ended'])],
            'change_reason' => ['required', 'string', 'max:5000'],
            'allow_conflicts' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $status = $this->input('status');
            $gameStatus = is_string($status) ? GameStatus::tryFrom($status) : null;

            if ($status === GameStatus::Final->value && (! $this->has('home_score') || ! $this->has('away_score'))) {
                $validator->errors()->add('home_score', 'Both official scores are required to finalize a game.');
            }

            if ($gameStatus?->requiresReason() && blank($this->input('status_reason'))) {
                $validator->errors()->add('status_reason', 'Explain this game status change for viewers and the audit history.');
            }

            $game = $this->route('game');
            $livestreamUrl = $this->has('livestream_url') ? $this->input('livestream_url') : $game?->livestream_url;
            if ($this->input('livestream_status') !== 'unavailable' && $this->has('livestream_status') && blank($livestreamUrl)) {
                $validator->errors()->add('livestream_url', 'Add an authorized livestream URL before publishing its status.');
            }
        }];
    }
}
