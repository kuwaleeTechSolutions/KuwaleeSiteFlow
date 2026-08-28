<?php

namespace App\Http\Requests\Measurement;

use App\Models\Measurement;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! is_numeric($this->input('site_id'))) {
            return true;
        }

        $site = Site::find($this->input('site_id'));

        if (! $site) {
            return true;
        }

        // A "revision" (revises_measurement_id set) is authorized the same
        // way as a fresh measurement for this site.
        return $this->user()->can('createForSite', [Measurement::class, $site]);
    }

    public function rules(): array
    {
        return [
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')],
            'measurement_date' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'revises_measurement_id' => ['nullable', 'integer', Rule::exists('measurements', 'id')],
            'items' => ['required', 'array', 'min:1'],
            'items.*.boq_item_id' => ['required', 'integer', Rule::exists('boq_items', 'id')],
            'items.*.current_quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
