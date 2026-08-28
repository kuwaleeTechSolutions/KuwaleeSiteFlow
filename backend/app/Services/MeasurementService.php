<?php

namespace App\Services;

use App\Models\BoqItem;
use App\Models\Measurement;
use App\Models\MeasurementItem;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeasurementService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function create(Site $site, array $data, User $actor): Measurement
    {
        return DB::transaction(function () use ($site, $data, $actor) {
            $measurement = Measurement::create([
                'organization_id' => $site->organization_id,
                'project_id' => $site->project_id,
                'site_id' => $site->id,
                'measurement_date' => $data['measurement_date'],
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $actor->id,
                'revises_measurement_id' => $data['revises_measurement_id'] ?? null,
            ]);

            foreach ($data['items'] as $itemData) {
                $boqItem = BoqItem::findOrFail($itemData['boq_item_id']);

                abort_unless(
                    (int) $boqItem->project_id === (int) $site->project_id,
                    422,
                    'The selected BOQ item does not belong to this site\'s project.'
                );

                $previousQuantity = $this->latestCumulativeForItemNumber($site->project_id, $boqItem->item_number);
                $currentQuantity = (string) $itemData['current_quantity'];
                $cumulativeQuantity = bcadd($previousQuantity, $currentQuantity, 3);

                // A measurement can never claim more cumulative progress
                // than the BOQ's contracted quantity for that item — this
                // is the guard against over-measurement.
                if (bccomp($cumulativeQuantity, (string) $boqItem->contract_quantity, 3) > 0) {
                    throw ValidationException::withMessages([
                        'items' => ["Cumulative quantity for item {$boqItem->item_number} ({$cumulativeQuantity}) would exceed the contracted quantity ({$boqItem->contract_quantity})."],
                    ]);
                }

                MeasurementItem::create([
                    'measurement_id' => $measurement->id,
                    'boq_item_id' => $boqItem->id,
                    'previous_quantity' => $previousQuantity,
                    'current_quantity' => $currentQuantity,
                    'cumulative_quantity' => $cumulativeQuantity,
                    'unit' => $boqItem->unit,
                    'remarks' => $itemData['remarks'] ?? null,
                ]);
            }

            $this->auditLog->log('measurement.created', $measurement, $actor);

            return $measurement;
        });
    }

    public function submit(Measurement $measurement, User $actor): Measurement
    {
        abort_unless($measurement->isEditable(), 422, 'Only draft measurements can be submitted.');

        $measurement->update(['status' => 'submitted', 'submitted_at' => now()]);
        $this->auditLog->log('measurement.submitted', $measurement, $actor);

        return $measurement;
    }

    public function approve(Measurement $measurement, User $actor): Measurement
    {
        abort_unless($measurement->status === 'submitted', 422, 'Only submitted measurements can be approved.');

        $measurement->update([
            'status' => 'approved',
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);

        $this->auditLog->log('measurement.approved', $measurement, $actor, ['status' => 'submitted'], ['status' => 'approved']);

        return $measurement;
    }

    public function reject(Measurement $measurement, User $actor, string $remarks): Measurement
    {
        abort_unless($measurement->status === 'submitted', 422, 'Only submitted measurements can be rejected.');

        $measurement->update([
            'status' => 'rejected',
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'review_remarks' => $remarks,
        ]);

        $this->auditLog->log('measurement.rejected', $measurement, $actor, ['status' => 'submitted'], ['status' => 'rejected']);

        return $measurement;
    }

    /**
     * The latest APPROVED cumulative quantity recorded for this
     * item_number, across ALL boq_item revisions that share it — this is
     * "previous_quantity" for the NEXT measurement entry. Zero if none
     * exists yet.
     */
    public function latestCumulativeForItemNumber(int $projectId, string $itemNumber): string
    {
        $boqItemIds = BoqItem::where('project_id', $projectId)->where('item_number', $itemNumber)->pluck('id');

        $latest = MeasurementItem::whereIn('boq_item_id', $boqItemIds)
            ->whereHas('measurement', fn ($q) => $q->where('status', 'approved'))
            ->join('measurements', 'measurement_items.measurement_id', '=', 'measurements.id')
            ->orderByDesc('measurements.approved_at')
            ->orderByDesc('measurement_items.id')
            ->select('measurement_items.*')
            ->first();

        return $latest ? (string) $latest->cumulative_quantity : '0';
    }
}
