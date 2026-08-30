<?php

namespace App\Http\Requests\DailyReport;

use App\Models\DailyReport;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyReportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_numeric($this->input('site_id'))) {
            // Resolve without the tenant scope so an attempted cross-org ID
            // reaches policy authorization and returns 403 rather than
            // leaking validation behaviour.
            $this->merge(['site_id' => Site::withoutGlobalScopes()->find($this->input('site_id'))?->uuid ?? $this->input('site_id')]);
        }
    }

    public function authorize(): bool
    {
        // Defer to validation's `required`/`exists` rules if site_id is
        // missing or malformed, rather than throwing here.
        if (! is_string($this->input('site_id'))) {
            return true;
        }

        $site = Site::where('uuid', $this->input('site_id'))->first();

        return $site !== null && $this->user()->can('createForSite', [DailyReport::class, $site]);
    }

    public function rules(): array
    {
        return [
            'site_id' => ['required', 'string', Rule::exists('sites', 'uuid')],
            'report_date' => ['required', 'date', 'before_or_equal:today'],
            'weather' => ['nullable', 'string', 'max:100'],
            'work_activities' => ['nullable', 'string', 'max:5000'],
            'work_completed' => ['nullable', 'string', 'max:5000'],
            'quantity_completed' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:30'],
            'manpower_deployed' => ['nullable', 'integer', 'min:0'],
            'equipment_used' => ['nullable', 'string', 'max:2000'],
            'material_used' => ['nullable', 'string', 'max:2000'],
            'problems_delays' => ['nullable', 'string', 'max:2000'],
            'reason_for_delay' => ['nullable', 'string', 'max:2000'],
            'safety_incidents' => ['nullable', 'string', 'max:2000'],
            'tomorrow_plan' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
