<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'boq_item' => $this->whenLoaded('boqItem', fn () => [
                'item_number' => $this->boqItem->item_number,
                'description' => $this->boqItem->description,
            ]),
            'quantity_billed' => (string) $this->quantity_billed,
            'rate' => (string) $this->rate,
            'amount' => (string) $this->amount,
        ];
    }
}
