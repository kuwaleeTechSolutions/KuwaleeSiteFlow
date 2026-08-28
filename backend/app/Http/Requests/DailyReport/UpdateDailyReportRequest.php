<?php

namespace App\Http\Requests\DailyReport;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDailyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('dailyReport'));
    }

    public function rules(): array
    {
        return [
            'report_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
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
