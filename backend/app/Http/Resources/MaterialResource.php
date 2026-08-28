<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'material_code' => $this->material_code,
            'material_name' => $this->material_name,
            'category' => $this->category,
            'unit' => $this->unit,
            'minimum_stock' => (string) $this->minimum_stock,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
