<?php

namespace App\Http\Requests\Attendance;

use App\Models\Site;
use App\Models\WorkerAttendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! is_numeric($this->input('site_id'))) {
            return true;
        }

        $site = Site::find($this->input('site_id'));

        return $site !== null && $this->user()->can('markForSite', [WorkerAttendance::class, $site]);
    }

    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')],
            'attendance_date' => ['required', 'date', 'before_or_equal:today'],
            'shift' => ['nullable', 'string', 'max:30'],
            'entries' => ['required', 'array', 'min:1', 'max:500'],
            'entries.*.worker_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('workers', 'id')->where('organization_id', $organizationId),
            ],
            'entries.*.status' => ['required', Rule::in(['present', 'absent', 'half_day'])],
            'entries.*.check_in' => ['nullable', 'date_format:H:i'],
            'entries.*.check_out' => ['nullable', 'date_format:H:i'],
            'entries.*.overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'entries.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
