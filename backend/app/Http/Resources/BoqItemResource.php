<?php

namespace App\Http\Resources;

use App\Services\MeasurementService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoqItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $completedQuantity = app(MeasurementService::class)
            ->latestCumulativeForItemNumber($this->project_id, $this->item_number);
        $remainingQuantity = bcsub((string) $this->contract_quantity, $completedQuantity, 3);
        $completedValue = bcmul($completedQuantity, (string) $this->contract_rate, 2);
        $remainingValue = bcsub((string) $this->contract_value, $completedValue, 2);
        $percentage = bccomp((string) $this->contract_quantity, '0', 3) > 0
            ? bcmul(bcdiv($completedQuantity, (string) $this->contract_quantity, 4), '100', 2)
            : '0.00';

        return [
            'id' => $this->uuid,
            'item_number' => $this->item_number,
            'description' => $this->description,
            'unit' => $this->unit,
            'contract_quantity' => (string) $this->contract_quantity,
            'contract_rate' => (string) $this->contract_rate,
            'contract_value' => (string) $this->contract_value,
            'completed_quantity' => $completedQuantity,
            'remaining_quantity' => $remainingQuantity,
            'completed_value' => $completedValue,
            'remaining_value' => $remainingValue,
            'percentage_progress' => $percentage,
            'revision_number' => $this->whenLoaded('revision', fn () => $this->revision->revision_number),
        ];
    }
}
