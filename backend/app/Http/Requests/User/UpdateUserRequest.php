<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        /** @var \App\Models\User $target */
        $target = $this->route('user');
        $organizationId = $this->user()->organization_id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes', 'required', 'email', 'max:255',
                Rule::unique('users', 'email')
                    ->where('organization_id', $organizationId)
                    ->ignore($target->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['sometimes', Rule::in(['active', 'invited', 'disabled'])],
        ];
    }
}
