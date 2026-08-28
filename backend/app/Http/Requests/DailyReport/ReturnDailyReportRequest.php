<?php

namespace App\Http\Requests\DailyReport;

use Illuminate\Foundation\Http\FormRequest;

class ReturnDailyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('returnForCorrection', $this->route('dailyReport'));
    }

    public function rules(): array
    {
        return [
            // Required: a supervisor needs to know WHY it was returned in
            // order to correct it.
            'review_remarks' => ['required', 'string', 'max:2000'],
        ];
    }
}
