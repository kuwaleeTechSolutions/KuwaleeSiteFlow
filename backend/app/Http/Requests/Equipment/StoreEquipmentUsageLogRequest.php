<?php

namespace App\Http\Requests\Equipment;

use App\Models\Site;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentUsageLogRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $changes = [];
        if (is_numeric($this->input('site_id'))) $changes['site_id'] = Site::find($this->input('site_id'))?->uuid ?? $this->input('site_id');
        if (is_numeric($this->input('equipment_id'))) $changes['equipment_id'] = Equipment::find($this->input('equipment_id'))?->uuid ?? $this->input('equipment_id');
        if (is_numeric($this->input('operator_id'))) $changes['operator_id'] = User::find($this->input('operator_id'))?->uuid ?? $this->input('operator_id');
        if ($changes) $this->merge($changes);
    }

    public function authorize(): bool
    {
        if (! is_string($this->input('site_id'))) {
            return true;
        }

        $site = Site::where('uuid', $this->input('site_id'))->first();

        return $site !== null && $this->user()->can('createForSite', [
            \App\Models\EquipmentUsageLog::class, $site,
        ]);
    }

    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            'equipment_id' => [
                'required', 'string',
                Rule::exists('equipment', 'uuid')->where('organization_id', $organizationId),
            ],
            'site_id' => ['required', 'string', Rule::exists('sites', 'uuid')],
            'usage_date' => ['required', 'date', 'before_or_equal:today'],
            'hours_used' => ['required', 'numeric', 'min:0', 'max:24'],
            'operator_id' => [
                'nullable', 'string',
                Rule::exists('users', 'uuid')->where('organization_id', $organizationId),
            ],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
