<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeasurementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'project_id' => $this->whenLoaded('project', fn () => $this->project->uuid),
            'site_id' => $this->whenLoaded('site', fn () => $this->site->uuid),
            'site_name' => $this->whenLoaded('site', fn () => $this->site->site_name),
            'measurement_date' => $this->measurement_date?->toDateString(),
            'remarks' => $this->remarks,
            'status' => $this->status,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->uuid, 'name' => $this->creator->name,
            ] : null),
            'submitted_at' => $this->submitted_at,
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? [
                'id' => $this->approver->uuid, 'name' => $this->approver->name,
            ] : null),
            'approved_at' => $this->approved_at,
            'review_remarks' => $this->review_remarks,
            'revises_measurement_id' => $this->whenLoaded('revisedMeasurement', fn () => $this->revisedMeasurement?->uuid),
            'is_editable' => $this->isEditable(),
            'items' => MeasurementItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
        ];
    }
}
