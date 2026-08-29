<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentUsageLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'equipment' => $this->whenLoaded('equipment', fn () => [
                'id' => $this->equipment->uuid, 'equipment_name' => $this->equipment->equipment_name,
            ]),
            'equipment_name' => $this->whenLoaded('equipment', fn () => $this->equipment->equipment_name),
            'project_id' => $this->whenLoaded('project', fn () => $this->project->uuid),
            'site_id' => $this->whenLoaded('site', fn () => $this->site->uuid),
            'site_name' => $this->whenLoaded('site', fn () => $this->site->site_name),
            'usage_date' => $this->usage_date?->toDateString(),
            'hours_used' => (string) $this->hours_used,
            'operator' => $this->whenLoaded('operator', fn () => $this->operator ? [
                'id' => $this->operator->uuid, 'name' => $this->operator->name,
            ] : null),
            'remarks' => $this->remarks,
            'created_at' => $this->created_at,
        ];
    }
}
