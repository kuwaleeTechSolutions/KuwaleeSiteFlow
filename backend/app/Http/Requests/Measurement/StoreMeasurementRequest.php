<?php

namespace App\Http\Requests\Measurement;

use App\Models\Measurement;
use App\Models\BoqItem;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeasurementRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // The API accepts the canonical items[] payload and a single-item
        // field-workflow payload. Normalising here keeps the mobile form
        // compact without bypassing validation or authorization.
        if (! $this->has('items') && $this->filled('boq_item_id')) {
            $this->merge(['items' => [[
                'boq_item_id' => $this->input('boq_item_id'),
                'current_quantity' => $this->input('current_quantity'),
                'remarks' => $this->input('item_remarks'),
            ]]]);
        }

        $changes = [];
        if (is_numeric($this->input('site_id'))) {
            $changes['site_id'] = Site::find($this->input('site_id'))?->uuid ?? $this->input('site_id');
        }
        if (is_numeric($this->input('revises_measurement_id'))) {
            $changes['revises_measurement_id'] = Measurement::find($this->input('revises_measurement_id'))?->uuid ?? $this->input('revises_measurement_id');
        }
        $items = $this->input('items');
        if (is_array($items)) {
            $changes['items'] = collect($items)->map(function (array $item) {
                if (isset($item['boq_item_id']) && is_numeric($item['boq_item_id'])) {
                    $item['boq_item_id'] = BoqItem::find($item['boq_item_id'])?->uuid ?? $item['boq_item_id'];
                }
                return $item;
            })->all();
        }
        if ($changes) {
            $this->merge($changes);
        }
    }

    public function authorize(): bool
    {
        if (! is_string($this->input('site_id'))) {
            return true;
        }

        $site = Site::where('uuid', $this->input('site_id'))->first();

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
            'site_id' => ['required', 'string', Rule::exists('sites', 'uuid')],
            'measurement_date' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'revises_measurement_id' => ['nullable', 'string', Rule::exists('measurements', 'uuid')],
            'items' => ['required', 'array', 'min:1'],
            'items.*.boq_item_id' => ['required', 'string', Rule::exists('boq_items', 'uuid')],
            'items.*.current_quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
