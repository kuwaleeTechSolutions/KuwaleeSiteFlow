<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'project_id' => $this->whenLoaded('project', fn () => $this->project->uuid),
            'site_code' => $this->site_code,
            'site_name' => $this->site_name,
            'location' => $this->location,
            'latitude' => $this->latitude !== null ? (string) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (string) $this->longitude : null,
            'site_manager' => $this->whenLoaded('siteManager', fn () => $this->siteManager ? [
                'id' => $this->siteManager->uuid,
                'name' => $this->siteManager->name,
            ] : null),
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
