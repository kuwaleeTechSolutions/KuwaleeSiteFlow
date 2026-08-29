<?php

namespace App\Http\Requests\Fuel;

use App\Models\FuelTransaction;
use App\Models\Equipment;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFuelTransactionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $changes = [];
        if (is_numeric($this->input('site_id'))) $changes['site_id'] = Site::find($this->input('site_id'))?->uuid ?? $this->input('site_id');
        if (is_numeric($this->input('equipment_id'))) $changes['equipment_id'] = Equipment::find($this->input('equipment_id'))?->uuid ?? $this->input('equipment_id');
        if ($changes) $this->merge($changes);
    }

    public function authorize(): bool
    {
        if (! is_string($this->input('site_id'))) {
            return true;
        }

        $site = Site::where('uuid', $this->input('site_id'))->first();

        return $site !== null && $this->user()->can('createForSite', [FuelTransaction::class, $site]);
    }

    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            'transaction_type' => ['required', Rule::in(['purchase', 'issue'])],
            // Required for 'issue' (fuel must be issued TO a specific piece
            // of equipment); optional for 'purchase' (bulk depot stock-in).
            'equipment_id' => [
                Rule::requiredIf(fn () => $this->input('transaction_type') === 'issue'),
                'nullable', 'string',
                Rule::exists('equipment', 'uuid')->where('organization_id', $organizationId),
            ],
            'site_id' => ['required', 'string', Rule::exists('sites', 'uuid')],
            'opening_reading' => ['nullable', 'numeric', 'min:0'],
            'closing_reading' => ['nullable', 'numeric', 'min:0', 'gt:opening_reading'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
