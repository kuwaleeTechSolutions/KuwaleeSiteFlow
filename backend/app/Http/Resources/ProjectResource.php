<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $today = Carbon::today();

        $daysElapsed = $this->start_date ? $this->start_date->diffInDays($today, false) : null;
        $daysRemaining = $this->expected_end_date ? $today->diffInDays($this->expected_end_date, false) : null;

        return [
            'id' => $this->uuid,
            'project_code' => $this->project_code,
            'project_name' => $this->project_name,
            'client_name' => $this->client_name,
            'contract_number' => $this->contract_number,
            // Cast to string to preserve decimal precision through JSON
            // serialization (avoids IEEE-754 float rounding on the client).
            'contract_value' => (string) $this->contract_value,
            'start_date' => $this->start_date?->toDateString(),
            'expected_end_date' => $this->expected_end_date?->toDateString(),
            'actual_end_date' => $this->actual_end_date?->toDateString(),
            'status' => $this->status,
            'project_manager' => $this->whenLoaded('projectManager', fn () => [
                'id' => $this->projectManager->uuid,
                'name' => $this->projectManager->name,
            ]),
            'description' => $this->description,
            'days_elapsed' => $daysElapsed !== null ? max(0, (int) $daysElapsed) : null,
            'days_remaining' => $daysElapsed !== null && $daysRemaining !== null ? (int) $daysRemaining : null,
            'is_overdue' => $daysRemaining !== null && $daysRemaining < 0 && ! in_array($this->status, ['completed', 'cancelled']),
            'sites_count' => $this->whenCounted('sites'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
