<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function record(Bill $bill, array $data, User $actor): Payment
    {
        return DB::transaction(function () use ($bill, $data, $actor) {
            // Lock the bill row so two concurrent payment submissions
            // against the same bill can't both pass the overpayment check
            // before either commits.
            $lockedBill = Bill::where('id', $bill->id)->lockForUpdate()->firstOrFail();

            $currentPaid = $lockedBill->paidAmount();
            $newAmount = (string) $data['amount'];
            $projectedTotal = bcadd($currentPaid, $newAmount, 2);

            if (bccomp($projectedTotal, (string) $lockedBill->net_payable, 2) > 0) {
                $remaining = bcsub((string) $lockedBill->net_payable, $currentPaid, 2);
                throw ValidationException::withMessages([
                    'amount' => ["This payment of {$newAmount} would exceed the bill's outstanding amount of {$remaining}."],
                ]);
            }

            $payment = Payment::create([
                'organization_id' => $lockedBill->organization_id,
                'bill_id' => $lockedBill->id,
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_date' => $data['payment_date'],
                'amount' => $newAmount,
                'payment_mode' => $data['payment_mode'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $actor->id,
            ]);

            $newStatus = bccomp($projectedTotal, (string) $lockedBill->net_payable, 2) === 0
                ? 'paid'
                : 'partially_paid';

            $lockedBill->update(['status' => $newStatus]);

            $this->auditLog->log('payment.created', $payment, $actor, null, [
                'bill_id' => $lockedBill->id,
                'amount' => $newAmount,
                'resulting_bill_status' => $newStatus,
            ]);

            return $payment;
        });
    }
}
