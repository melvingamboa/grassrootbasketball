<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class MediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']];
    }
}
