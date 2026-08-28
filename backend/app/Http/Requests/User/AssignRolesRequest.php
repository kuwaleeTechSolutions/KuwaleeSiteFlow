<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignRole', $this->route('user'));
    }

    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => [
                'integer',
                Rule::exists('roles', 'id')->where('organization_id', $organizationId),
            ],
        ];
    }
}
