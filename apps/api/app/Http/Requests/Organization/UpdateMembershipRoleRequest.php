<?php

namespace App\Http\Requests\Organization;

use App\Enums\OrganizationRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMembershipRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['role' => ['required', Rule::enum(OrganizationRole::class)]];
    }
}
