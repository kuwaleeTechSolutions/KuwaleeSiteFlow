<?php

namespace App\Http\Requests\Equipment;

use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentUsageLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! is_numeric($this->input('site_id'))) {
            return true;
        }

        $site = Site::find($this->input('site_id'));

        return $site !== null && $this->user()->can('createForSite', [
            \App\Models\EquipmentUsageLog::class, $site,
        ]);
    }

    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            'equipment_id' => [
                'required', 'integer',
                Rule::exists('equipment', 'id')->where('organization_id', $organizationId),
            ],
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')],
            'usage_date' => ['required', 'date', 'before_or_equal:today'],
            'hours_used' => ['required', 'numeric', 'min:0', 'max:24'],
            'operator_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
            ],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
