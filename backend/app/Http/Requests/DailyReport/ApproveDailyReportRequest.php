<?php

namespace App\Http\Requests\DailyReport;

use Illuminate\Foundation\Http\FormRequest;

class ApproveDailyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('approve', $this->route('dailyReport'));
    }

    public function rules(): array
    {
        return [
            'review_remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
