<?php

namespace App\Http\Requests\Compliance;

use App\Models\ComplianceItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComplianceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ComplianceItem::class);
    }

    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            'document_id' => [
                'nullable', 'integer',
                Rule::exists('documents', 'id')->where('organization_id', $organizationId),
            ],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([
                'insurance', 'labour_licence', 'equipment_certificate', 'calibration', 'vehicle_document', 'other',
            ])],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['required', 'date'],
            'responsible_person_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
            ],
            'related_entity_type' => ['nullable', Rule::in(['project', 'site', 'equipment', 'worker', 'organization'])],
            'related_entity_id' => ['nullable', 'integer', 'required_with:related_entity_type'],
        ];
    }
}
