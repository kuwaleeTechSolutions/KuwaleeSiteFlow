<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'bill_id' => $this->whenLoaded('bill', fn () => $this->bill->uuid),
            'payment_reference' => $this->payment_reference,
            'payment_date' => $this->payment_date?->toDateString(),
            'amount' => (string) $this->amount,
            'payment_mode' => $this->payment_mode,
            'remarks' => $this->remarks,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->uuid, 'name' => $this->creator->name,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
