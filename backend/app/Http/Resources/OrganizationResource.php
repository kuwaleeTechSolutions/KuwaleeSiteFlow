<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'gst_number' => $this->gst_number,
            'status' => $this->status,
            'settings' => $this->settings,
            'created_at' => $this->created_at,
        ];
    }
}
