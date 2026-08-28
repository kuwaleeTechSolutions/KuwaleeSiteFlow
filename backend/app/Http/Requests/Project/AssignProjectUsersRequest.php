<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignProjectUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignUsers', $this->route('project'));
    }

    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
            ],
        ];
    }
}
