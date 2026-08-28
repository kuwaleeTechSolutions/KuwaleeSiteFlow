<?php

namespace App\Http\Requests\Project;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Project::class);
    }

    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            'project_code' => [
                'required', 'string', 'max:60',
                Rule::unique('projects', 'project_code')->where('organization_id', $organizationId),
            ],
            'project_name' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'contract_number' => ['nullable', 'string', 'max:100'],
            'contract_value' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'start_date' => ['nullable', 'date'],
            'expected_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => [Rule::in(['planning', 'active', 'on_hold', 'completed', 'cancelled'])],
            'project_manager_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
