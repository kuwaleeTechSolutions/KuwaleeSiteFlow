<?php

namespace App\Http\Requests\Measurement;

use Illuminate\Foundation\Http\FormRequest;

class RejectMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reject', $this->route('measurement'));
    }

    public function rules(): array
    {
        return [
            'review_remarks' => ['required', 'string', 'max:2000'],
        ];
    }
}
