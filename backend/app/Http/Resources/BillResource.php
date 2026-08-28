<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'project_id' => $this->whenLoaded('project', fn () => $this->project->uuid),
            'bill_number' => $this->bill_number,
            'bill_type' => $this->bill_type,
            'bill_date' => $this->bill_date?->toDateString(),
            'billing_period_start' => $this->billing_period_start?->toDateString(),
            'billing_period_end' => $this->billing_period_end?->toDateString(),
            'previous_certified_amount' => (string) $this->previous_certified_amount,
            'current_work_value' => (string) $this->current_work_value,
            'deductions' => (string) $this->deductions,
            'taxes' => (string) $this->taxes,
            'net_payable' => (string) $this->net_payable,
            'paid_amount' => $this->paidAmount(),
            'outstanding_amount' => $this->outstandingAmount(),
            'status' => $this->status,
            'certifier' => $this->whenLoaded('certifier', fn () => $this->certifier ? [
                'id' => $this->certifier->uuid, 'name' => $this->certifier->name,
            ] : null),
            'certified_at' => $this->certified_at,
            'items' => BillItemResource::collection($this->whenLoaded('items')),
            'is_editable' => $this->isEditable(),
            'created_at' => $this->created_at,
        ];
    }
}
