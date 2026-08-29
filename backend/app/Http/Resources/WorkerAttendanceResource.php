<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkerAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'worker' => $this->whenLoaded('worker', fn () => [
                'id' => $this->worker->uuid,
                'name' => $this->worker->name,
                'worker_code' => $this->worker->worker_code,
            ]),
            'worker_name' => $this->whenLoaded('worker', fn () => $this->worker->name),
            'project_id' => $this->whenLoaded('project', fn () => $this->project->uuid),
            'site_id' => $this->whenLoaded('site', fn () => $this->site->uuid),
            'site_name' => $this->whenLoaded('site', fn () => $this->site->site_name),
            'attendance_date' => $this->attendance_date?->toDateString(),
            'shift' => $this->shift,
            'status' => $this->status,
            'check_in' => $this->check_in,
            'check_out' => $this->check_out,
            'overtime_hours' => (string) $this->overtime_hours,
            'remarks' => $this->remarks,
            'marked_by' => $this->whenLoaded('markedBy', fn () => $this->markedBy ? [
                'id' => $this->markedBy->uuid, 'name' => $this->markedBy->name,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
