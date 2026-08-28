<?php

namespace App\Http\Requests\Material;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('material'));
    }

    public function rules(): array
    {
        /** @var \App\Models\Material $material */
        $material = $this->route('material');
        $organizationId = $this->user()->organization_id;

        return [
            'material_code' => [
                'sometimes', 'required', 'string', 'max:60',
                Rule::unique('materials', 'material_code')
                    ->where('organization_id', $organizationId)
                    ->ignore($material->id),
            ],
            'material_name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'unit' => ['sometimes', 'required', 'string', 'max:30'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
