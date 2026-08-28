<?php

namespace App\Http\Requests\Bill;

use App\Models\Bill;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return $this->user()->can('createForProject', [Bill::class, $project]);
    }

    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'bill_number' => [
                'required', 'string', 'max:100',
                Rule::unique('bills', 'bill_number')->where('project_id', $project->id),
            ],
            'bill_type' => ['required', Rule::in(['running', 'interim', 'final'])],
            'bill_date' => ['required', 'date'],
            'billing_period_start' => ['required', 'date'],
            'billing_period_end' => ['required', 'date', 'after_or_equal:billing_period_start'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'taxes' => ['nullable', 'numeric', 'min:0'],
            // Each line bills a quantity against a SPECIFIC approved
            // measurement_item — never a raw, unmeasured quantity.
            'items' => ['required', 'array', 'min:1'],
            'items.*.measurement_item_id' => ['required', 'integer', Rule::exists('measurement_items', 'id')],
            'items.*.quantity_billed' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
