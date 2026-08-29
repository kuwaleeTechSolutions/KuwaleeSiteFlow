<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'material' => $this->whenLoaded('material', fn () => [
                'id' => $this->material->uuid,
                'material_name' => $this->material->material_name,
                'unit' => $this->material->unit,
            ]),
            'material_name' => $this->whenLoaded('material', fn () => $this->material->material_name),
            'transaction_type' => $this->transaction_type,
            'direction' => $this->direction,
            'quantity' => (string) $this->quantity,
            'project_id' => $this->whenLoaded('project', fn () => $this->project->uuid),
            'site_id' => $this->whenLoaded('site', fn () => $this->site->uuid),
            'site_name' => $this->whenLoaded('site', fn () => $this->site->site_name),
            'to_site_id' => $this->whenLoaded('toSite', fn () => $this->toSite?->uuid),
            'reference_number' => $this->reference_number,
            'remarks' => $this->remarks,
            'is_override' => $this->is_override,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->uuid, 'name' => $this->creator->name,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
