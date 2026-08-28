<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplianceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'document_id' => $this->whenLoaded('document', fn () => $this->document?->uuid),
            'title' => $this->title,
            'type' => $this->type,
            'issue_date' => $this->issue_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'days_until_expiry' => $this->daysUntilExpiry(),
            'responsible_person' => $this->whenLoaded('responsiblePerson', fn () => $this->responsiblePerson ? [
                'id' => $this->responsiblePerson->uuid, 'name' => $this->responsiblePerson->name,
            ] : null),
            'related_entity_type' => $this->related_entity_type,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
