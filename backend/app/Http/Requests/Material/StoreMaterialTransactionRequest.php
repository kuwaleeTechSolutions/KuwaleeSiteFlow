<?php

namespace App\Http\Requests\Material;

use App\Models\MaterialTransaction;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! is_numeric($this->input('site_id')) || ! is_string($this->input('transaction_type'))) {
            return true; // defer to validation for missing/malformed input
        }

        $site = Site::find($this->input('site_id'));

        return $site !== null && $this->user()->can(
            'createForSite',
            [MaterialTransaction::class, $site, $this->input('transaction_type')]
        );
    }

    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            'material_id' => [
                'required', 'integer',
                Rule::exists('materials', 'id')->where('organization_id', $organizationId),
            ],
            'transaction_type' => ['required', Rule::in(['inward', 'issue', 'return', 'transfer', 'adjustment'])],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')],
            // Required only for 'transfer' — the destination site, which
            // must differ from the source site.
            'to_site_id' => [
                Rule::requiredIf(fn () => $this->input('transaction_type') === 'transfer'),
                'nullable', 'integer', 'different:site_id',
                Rule::exists('sites', 'id'),
            ],
            // Required only for 'adjustment' — direction is otherwise
            // implied by transaction_type and must NOT be supplied.
            'direction' => [
                Rule::requiredIf(fn () => $this->input('transaction_type') === 'adjustment'),
                Rule::prohibitedIf(fn () => $this->input('transaction_type') !== 'adjustment'),
                Rule::in(['increase', 'decrease']),
            ],
            // Allows a caller holding 'materials.negative_stock_override' to
            // explicitly force the transaction through insufficient stock.
            // Ignored (and re-validated against the permission) otherwise.
            'force_override' => ['boolean'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
