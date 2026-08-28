<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeasurementItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'boq_item' => $this->whenLoaded('boqItem', fn () => [
                'id' => $this->boqItem->uuid,
                'item_number' => $this->boqItem->item_number,
                'description' => $this->boqItem->description,
            ]),
            'previous_quantity' => (string) $this->previous_quantity,
            'current_quantity' => (string) $this->current_quantity,
            'cumulative_quantity' => (string) $this->cumulative_quantity,
            'unit' => $this->unit,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at,
        ];
    }
}
