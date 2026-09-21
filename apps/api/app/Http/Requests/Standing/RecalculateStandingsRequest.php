<?php

namespace App\Http\Requests\Standing;

use Illuminate\Foundation\Http\FormRequest;

class RecalculateStandingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'division_id' => ['required', 'integer', 'exists:divisions,id'],
        ];
    }
}
