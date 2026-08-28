<?php

namespace App\Http\Requests\Equipment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('equipment'));
    }

    public function rules(): array
    {
        /** @var \App\Models\Equipment $equipment */
        $equipment = $this->route('equipment');
        $organizationId = $this->user()->organization_id;

        return [
            'equipment_code' => [
                'sometimes', 'required', 'string', 'max:60',
                Rule::unique('equipment', 'equipment_code')
                    ->where('organization_id', $organizationId)
                    ->ignore($equipment->id),
            ],
            'equipment_name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'assigned_project_id' => [
                'nullable', 'integer',
                Rule::exists('projects', 'id')->where('organization_id', $organizationId),
            ],
            'assigned_site_id' => ['nullable', 'integer', Rule::exists('sites', 'id')],
            'current_operator_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
            ],
            'status' => ['sometimes', Rule::in(['available', 'in_use', 'maintenance', 'breakdown', 'inactive'])],
        ];
    }
}
