<?php

namespace App\Http\Requests\Competition;

use App\Enums\SeasonFormat;
use App\Enums\SeasonStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertSeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'primary_venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'timezone' => [$required, 'timezone'],
            'format' => [$required, Rule::enum(SeasonFormat::class)],
            'status' => [$required, Rule::enum(SeasonStatus::class)],
            'max_roster_size' => [$required, 'integer', 'between:1,20'],
            'period_count' => [$required, 'integer', 'between:1,8'],
            'period_minutes' => [$required, 'integer', 'between:1,20'],
            'overtime_minutes' => [$required, 'integer', 'between:1,10'],
            'rules_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
