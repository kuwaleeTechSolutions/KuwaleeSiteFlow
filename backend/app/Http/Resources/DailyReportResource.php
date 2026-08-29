<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->uuid,
                'project_name' => $this->project->project_name,
            ]),
            'site' => $this->whenLoaded('site', fn () => [
                'id' => $this->site->uuid,
                'site_name' => $this->site->site_name,
            ]),
            'site_name' => $this->whenLoaded('site', fn () => $this->site->site_name),
            'report_date' => $this->report_date?->toDateString(),
            'weather' => $this->weather,
            'work_activities' => $this->work_activities,
            'work_completed' => $this->work_completed,
            'quantity_completed' => $this->quantity_completed !== null ? (string) $this->quantity_completed : null,
            'unit' => $this->unit,
            'manpower_deployed' => $this->manpower_deployed,
            'equipment_used' => $this->equipment_used,
            'material_used' => $this->material_used,
            'problems_delays' => $this->problems_delays,
            'reason_for_delay' => $this->reason_for_delay,
            'safety_incidents' => $this->safety_incidents,
            'tomorrow_plan' => $this->tomorrow_plan,
            'remarks' => $this->remarks,
            'status' => $this->status,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->uuid, 'name' => $this->creator->name,
            ] : null),
            'submitted_at' => $this->submitted_at,
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? [
                'id' => $this->reviewer->uuid, 'name' => $this->reviewer->name,
            ] : null),
            'reviewed_at' => $this->reviewed_at,
            'review_remarks' => $this->review_remarks,
            'photos' => DailyReportPhotoResource::collection($this->whenLoaded('photos')),
            'is_editable' => $this->isEditable(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
