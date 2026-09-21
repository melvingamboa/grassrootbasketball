<?php

namespace App\Http\Requests\Team;

use App\Enums\PlayerPosition;
use App\Enums\RegistrationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAndRegisterPlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:16'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'jersey_number' => ['required', 'integer', 'between:0,99'],
            'position' => ['required', Rule::enum(PlayerPosition::class)],
            'status' => ['required', Rule::enum(RegistrationStatus::class)],
        ];
    }
}
