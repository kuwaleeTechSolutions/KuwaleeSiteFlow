<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WageComputationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'worker' => $this->whenLoaded('worker', fn () => [
                'id' => $this->worker->uuid,
                'name' => $this->worker->name,
            ]),
            'project_id' => $this->whenLoaded('project', fn () => $this->project->uuid),
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'days_present' => (string) $this->days_present,
            'overtime_hours' => (string) $this->overtime_hours,
            'base_wage_total' => (string) $this->base_wage_total,
            'overtime_total' => (string) $this->overtime_total,
            'gross_total' => (string) $this->gross_total,
            'generated_at' => $this->generated_at,
        ];
    }
}
