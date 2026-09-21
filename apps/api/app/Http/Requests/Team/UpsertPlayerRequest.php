<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class UpsertPlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'first_name' => [$required, 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => [$required, 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:16'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'is_active' => [$required, 'boolean'],
        ];
    }
}
