<?php

namespace App\Http\Requests\Attendance;

use App\Models\Site;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $changes = [];
        if (is_numeric($this->input('site_id'))) $changes['site_id'] = Site::find($this->input('site_id'))?->uuid ?? $this->input('site_id');
        if (is_numeric($this->input('worker_id'))) $changes['worker_id'] = Worker::find($this->input('worker_id'))?->uuid ?? $this->input('worker_id');
        if ($changes) $this->merge($changes);
    }

    public function authorize(): bool
    {
        if (! is_string($this->input('site_id'))) {
            return true; // let validation report the missing/invalid field
        }

        $site = Site::where('uuid', $this->input('site_id'))->first();

        return $site !== null && $this->user()->can('markForSite', [WorkerAttendance::class, $site]);
    }

    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            'site_id' => ['required', 'string', Rule::exists('sites', 'uuid')],
            'worker_id' => [
                'required', 'string',
                Rule::exists('workers', 'uuid')->where('organization_id', $organizationId),
            ],
            'attendance_date' => ['required', 'date', 'before_or_equal:today'],
            'shift' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['present', 'absent', 'half_day'])],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i', 'after:check_in'],
            'overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
