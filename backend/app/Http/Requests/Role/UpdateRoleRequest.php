<?php

namespace App\Http\Requests\Role;

use App\Support\PermissionCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('role'));
    }

    public function rules(): array
    {
        /** @var \App\Models\Role $role */
        $role = $this->route('role');
        $organizationId = $this->user()->organization_id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'required', 'string', 'max:80', 'alpha_dash',
                Rule::unique('roles', 'slug')->where('organization_id', $organizationId)->ignore($role->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'org_wide_visibility' => ['boolean'],
            'permissions' => ['sometimes', 'array', 'min:1'],
            'permissions.*' => [Rule::in(PermissionCatalogue::flat())],
        ];
    }
}
