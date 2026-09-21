<?php

namespace App\Http\Requests\Competition;

use App\Enums\DivisionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertDivisionRequest extends FormRequest
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
            'category' => [$required, Rule::enum(DivisionCategory::class)],
            'gender' => [$required, Rule::in(['open', 'male', 'female', 'mixed'])],
            'minimum_age' => ['nullable', 'integer', 'between:5,100'],
            'maximum_age' => ['nullable', 'integer', 'between:5,100', 'gte:minimum_age'],
            'is_active' => [$required, 'boolean'],
        ];
    }
}
