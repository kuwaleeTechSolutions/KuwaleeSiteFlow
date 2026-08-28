<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'equipment_code' => $this->equipment_code,
            'equipment_name' => $this->equipment_name,
            'type' => $this->type,
            'registration_number' => $this->registration_number,
            'assigned_project' => $this->whenLoaded('assignedProject', fn () => $this->assignedProject ? [
                'id' => $this->assignedProject->uuid, 'project_name' => $this->assignedProject->project_name,
            ] : null),
            'assigned_site' => $this->whenLoaded('assignedSite', fn () => $this->assignedSite ? [
                'id' => $this->assignedSite->uuid, 'site_name' => $this->assignedSite->site_name,
            ] : null),
            'current_operator' => $this->whenLoaded('currentOperator', fn () => $this->currentOperator ? [
                'id' => $this->currentOperator->uuid, 'name' => $this->currentOperator->name,
            ] : null),
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
