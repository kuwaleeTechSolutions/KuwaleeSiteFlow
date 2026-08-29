<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FuelTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'transaction_type' => $this->transaction_type,
            'equipment' => $this->whenLoaded('equipment', fn () => $this->equipment ? [
                'id' => $this->equipment->uuid, 'equipment_name' => $this->equipment->equipment_name,
            ] : null),
            'equipment_name' => $this->whenLoaded('equipment', fn () => $this->equipment?->equipment_name),
            'project_id' => $this->whenLoaded('project', fn () => $this->project->uuid),
            'site_id' => $this->whenLoaded('site', fn () => $this->site->uuid),
            'opening_reading' => $this->opening_reading !== null ? (string) $this->opening_reading : null,
            'closing_reading' => $this->closing_reading !== null ? (string) $this->closing_reading : null,
            'quantity' => (string) $this->quantity,
            'unit_cost' => $this->unit_cost !== null ? (string) $this->unit_cost : null,
            'total_cost' => $this->total_cost !== null ? (string) $this->total_cost : null,
            'consumption_rate' => $this->consumptionRate(),
            'recorded_by' => $this->whenLoaded('recorder', fn () => $this->recorder ? [
                'id' => $this->recorder->uuid, 'name' => $this->recorder->name,
            ] : null),
            'reviewed_by' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? [
                'id' => $this->reviewer->uuid, 'name' => $this->reviewer->name,
            ] : null),
            'reviewed_at' => $this->reviewed_at,
            'is_reviewed' => $this->isReviewed(),
            'remarks' => $this->remarks,
            'created_at' => $this->created_at,
        ];
    }
}
