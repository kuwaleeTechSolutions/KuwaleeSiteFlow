<?php

namespace App\Http\Requests\Fuel;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFuelTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('fuelTransaction'));
    }

    public function rules(): array
    {
        return [
            'opening_reading' => ['nullable', 'numeric', 'min:0'],
            'closing_reading' => ['nullable', 'numeric', 'min:0', 'gt:opening_reading'],
            'quantity' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
