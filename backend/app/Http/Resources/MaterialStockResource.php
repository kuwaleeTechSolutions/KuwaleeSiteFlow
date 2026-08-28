<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialStockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'material' => $this->whenLoaded('material', fn () => [
                'id' => $this->material->uuid,
                'material_code' => $this->material->material_code,
                'material_name' => $this->material->material_name,
                'unit' => $this->material->unit,
                'minimum_stock' => (string) $this->material->minimum_stock,
            ]),
            'project_id' => $this->whenLoaded('project', fn () => $this->project->uuid),
            'site_id' => $this->whenLoaded('site', fn () => $this->site->uuid),
            'quantity_on_hand' => (string) $this->quantity_on_hand,
            'is_low_stock' => $this->relationLoaded('material') ? $this->isLowStock() : null,
            'updated_at' => $this->updated_at,
        ];
    }
}
