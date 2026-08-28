<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\MeasurementItem;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function create(Project $project, array $data, User $actor): Bill
    {
        return DB::transaction(function () use ($project, $data, $actor) {
            $previousCertified = $this->sumOfPriorCertifiedBills($project);

            $bill = Bill::create([
                'organization_id' => $project->organization_id,
                'project_id' => $project->id,
                'bill_number' => $data['bill_number'],
                'bill_type' => $data['bill_type'],
                'bill_date' => $data['bill_date'],
                'billing_period_start' => $data['billing_period_start'],
                'billing_period_end' => $data['billing_period_end'],
                'previous_certified_amount' => $previousCertified,
                'deductions' => $data['deductions'] ?? 0,
                'taxes' => $data['taxes'] ?? 0,
                'created_by' => $actor->id,
            ]);

            $currentWorkValue = '0';

            foreach ($data['items'] as $itemData) {
                $measurementItem = MeasurementItem::with('measurement', 'boqItem')
                    ->findOrFail($itemData['measurement_item_id']);

                abort_unless(
                    $measurementItem->measurement->status === 'approved',
                    422,
                    "Measurement item {$measurementItem->id} has not been approved and cannot be billed."
                );
                abort_unless(
                    (int) $measurementItem->measurement->project_id === (int) $project->id,
                    422,
                    'The selected measurement item does not belong to this project.'
                );

                $quantityBilled = (string) $itemData['quantity_billed'];
                $alreadyBilled = $this->totalBilledForBoqItemNumber($project, $measurementItem->boqItem->item_number);
                $measuredTotal = (string) $measurementItem->cumulative_quantity;
                $availableToBill = bcsub($measuredTotal, $alreadyBilled, 3);

                if (bccomp($quantityBilled, $availableToBill, 3) > 0) {
                    throw ValidationException::withMessages([
                        'items' => ["Cannot bill {$quantityBilled} for item {$measurementItem->boqItem->item_number} — only {$availableToBill} has been measured and approved but not yet billed."],
                    ]);
                }

                $rate = (string) $measurementItem->boqItem->contract_rate;
                $amount = bcmul($quantityBilled, $rate, 2);

                BillItem::create([
                    'bill_id' => $bill->id,
                    'measurement_item_id' => $measurementItem->id,
                    'boq_item_id' => $measurementItem->boq_item_id,
                    'quantity_billed' => $quantityBilled,
                    'rate' => $rate,
                    'amount' => $amount,
                ]);

                $currentWorkValue = bcadd($currentWorkValue, $amount, 2);
            }

            $netPayable = $this->computeNetPayable($currentWorkValue, (string) $bill->deductions, (string) $bill->taxes);

            $bill->update([
                'current_work_value' => $currentWorkValue,
                'net_payable' => $netPayable,
            ]);

            $this->auditLog->log('bill.created', $bill, $actor, null, [
                'bill_number' => $bill->bill_number,
                'current_work_value' => $currentWorkValue,
                'net_payable' => $netPayable,
            ]);

            return $bill->fresh();
        });
    }

    public function certify(Bill $bill, User $actor): Bill
    {
        abort_unless($bill->status === 'submitted', 422, 'Only submitted bills can be certified.');

        $bill->update([
            'status' => 'certified',
            'certified_by' => $actor->id,
            'certified_at' => now(),
        ]);

        $this->auditLog->log('bill.certified', $bill, $actor, ['status' => 'submitted'], ['status' => 'certified']);

        return $bill;
    }

    public function submit(Bill $bill, User $actor): Bill
    {
        abort_unless($bill->isEditable(), 422, 'Only draft bills can be submitted.');

        $bill->update(['status' => 'submitted']);
        $this->auditLog->log('bill.submitted', $bill, $actor);

        return $bill;
    }

    /**
     * net_payable = current_work_value - deductions - taxes.
     *
     * ASSUMPTION (documented for the customer to confirm): 'taxes' here is
     * treated as tax DEDUCTED AT SOURCE (TDS) — a subtraction from the
     * payable amount, consistent with common Indian government/PSU
     * contracting practice (matching this platform's Oil India Limited
     * context). If the organization's actual workflow instead ADDS GST on
     * top of the work value, this is the one place to flip the sign.
     */
    private function computeNetPayable(string $currentWorkValue, string $deductions, string $taxes): string
    {
        $afterDeductions = bcsub($currentWorkValue, $deductions, 2);

        return bcsub($afterDeductions, $taxes, 2);
    }

    private function sumOfPriorCertifiedBills(Project $project): string
    {
        return Bill::where('project_id', $project->id)
            ->whereIn('status', ['certified', 'partially_paid', 'paid'])
            ->pluck('current_work_value')
            ->reduce(fn (string $carry, $value) => bcadd($carry, (string) $value, 2), '0');
    }

    private function totalBilledForBoqItemNumber(Project $project, string $itemNumber): string
    {
        $boqItemIds = \App\Models\BoqItem::where('project_id', $project->id)
            ->where('item_number', $itemNumber)
            ->pluck('id');

        return BillItem::whereIn('boq_item_id', $boqItemIds)
            ->whereHas('bill', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->pluck('quantity_billed')
            ->reduce(fn (string $carry, $qty) => bcadd($carry, (string) $qty, 3), '0');
    }
}
