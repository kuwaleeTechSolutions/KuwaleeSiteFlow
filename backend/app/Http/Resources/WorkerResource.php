<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'worker_code' => $this->worker_code,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'trade' => $this->trade,
            'skill_category' => $this->skill_category,
            // CRITICAL: daily_wage is only included if the authenticated
            // user holds the 'labour.wages' permission — per brief §15
            // "Financial visibility should be permission-controlled." This
            // is enforced here, at the Resource layer, so it is impossible
            // to leak via this endpoint regardless of which controller
            // action rendered it.
            'daily_wage' => $this->when(
                $request->user()?->hasPermission('labour.wages'),
                fn () => (string) $this->daily_wage
            ),
            'joining_date' => $this->joining_date?->toDateString(),
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
