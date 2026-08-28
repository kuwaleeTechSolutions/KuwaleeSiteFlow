<?php

namespace App\Http\Requests\Fuel;

use App\Models\FuelTransaction;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFuelTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! is_numeric($this->input('site_id'))) {
            return true;
        }

        $site = Site::find($this->input('site_id'));

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
                'nullable', 'integer',
                Rule::exists('equipment', 'id')->where('organization_id', $organizationId),
            ],
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')],
            'opening_reading' => ['nullable', 'numeric', 'min:0'],
            'closing_reading' => ['nullable', 'numeric', 'min:0', 'gt:opening_reading'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
