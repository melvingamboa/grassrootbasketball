<?php

namespace App\Http\Requests\Bracket;

use App\Enums\BracketStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBracketRequest extends FormRequest
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
            'division_id' => ['required', 'integer', 'exists:divisions,id'],
            'name' => ['required', 'string', 'max:120'],
            'status' => ['required', Rule::enum(BracketStatus::class)],
            'template' => ['sometimes', Rule::in(['empty', 'single_elimination_4', 'single_elimination_8'])],
        ];
    }
}
