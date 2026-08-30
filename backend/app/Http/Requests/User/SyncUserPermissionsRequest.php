<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Support\PermissionCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncUserPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignRole', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'permission_names' => ['present', 'array'],
            'permission_names.*' => ['string', Rule::in(PermissionCatalogue::flat())],
        ];
    }
}
