<?php

namespace App\Http\Requests\Material;

use App\Models\Material;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Material::class);
    }

    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            'material_code' => [
                'required', 'string', 'max:60',
                Rule::unique('materials', 'material_code')->where('organization_id', $organizationId),
            ],
            'material_name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:30'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'status' => [Rule::in(['active', 'inactive'])],
        ];
    }
}
