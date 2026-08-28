<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    public function rules(): array
    {
        /** @var \App\Models\Project $project */
        $project = $this->route('project');
        $organizationId = $this->user()->organization_id;

        return [
            'project_code' => [
                'sometimes', 'required', 'string', 'max:60',
                Rule::unique('projects', 'project_code')
                    ->where('organization_id', $organizationId)
                    ->ignore($project->id),
            ],
            'project_name' => ['sometimes', 'required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'contract_number' => ['nullable', 'string', 'max:100'],
            'contract_value' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999999999.99'],
            'start_date' => ['nullable', 'date'],
            'expected_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'actual_end_date' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['planning', 'active', 'on_hold', 'completed', 'cancelled'])],
            'project_manager_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
