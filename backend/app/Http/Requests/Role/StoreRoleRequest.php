<?php

namespace App\Http\Requests\Role;

use App\Support\PermissionCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Role::class);
    }

    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:80', 'alpha_dash',
                Rule::unique('roles', 'slug')->where('organization_id', $organizationId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'org_wide_visibility' => ['boolean'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => [Rule::in(PermissionCatalogue::flat())],
        ];
    }
}
