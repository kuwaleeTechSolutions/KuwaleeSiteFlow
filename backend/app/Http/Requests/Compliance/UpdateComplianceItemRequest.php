<?php

namespace App\Http\Requests\Compliance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplianceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('complianceItem'));
    }

    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['sometimes', 'required', 'date'],
            'responsible_person_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
            ],
        ];
    }
}
