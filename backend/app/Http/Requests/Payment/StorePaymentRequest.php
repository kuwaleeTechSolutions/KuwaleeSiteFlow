<?php

namespace App\Http\Requests\Payment;

use App\Models\Bill;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Bill $bill */
        $bill = $this->route('bill');

        return $this->user()->can('createForBill', [\App\Models\Payment::class, $bill]);
    }

    public function rules(): array
    {
        return [
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_mode' => ['nullable', 'string', 'max:60'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
