<?php

namespace App\Http\Requests\Game;

use App\Enums\AnnouncementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:255'],
            'body' => [$required, 'string', 'max:10000'],
            'status' => [$required, Rule::enum(AnnouncementStatus::class)],
        ];
    }
}
