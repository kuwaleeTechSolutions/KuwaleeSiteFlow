<?php

namespace App\Services;

use App\Models\FuelTransaction;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FuelTransactionService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function record(Site $site, array $data, User $actor): FuelTransaction
    {
        return DB::transaction(function () use ($site, $data, $actor) {
            $totalCost = isset($data['unit_cost'])
                ? bcmul((string) $data['quantity'], (string) $data['unit_cost'], 2)
                : null;

            $transaction = FuelTransaction::create(array_merge($data, [
                'organization_id' => $site->organization_id,
                'project_id' => $site->project_id,
                'site_id' => $site->id,
                'total_cost' => $totalCost,
                'recorded_by' => $actor->id,
            ]));

            $this->auditLog->log('fuel_transaction.created', $transaction, $actor, null, [
                'transaction_type' => $transaction->transaction_type,
                'quantity' => (string) $transaction->quantity,
            ]);

            return $transaction;
        });
    }

    public function update(FuelTransaction $transaction, array $data, User $actor): FuelTransaction
    {
        $oldValues = $transaction->only(['quantity', 'unit_cost', 'opening_reading', 'closing_reading']);

        if (isset($data['unit_cost']) || isset($data['quantity'])) {
            $quantity = $data['quantity'] ?? $transaction->quantity;
            $unitCost = $data['unit_cost'] ?? $transaction->unit_cost;
            $data['total_cost'] = $unitCost !== null ? bcmul((string) $quantity, (string) $unitCost, 2) : null;
        }

        $transaction->update($data);

        $this->auditLog->log('fuel_transaction.updated', $transaction, $actor, $oldValues, $data);

        return $transaction;
    }

    public function review(FuelTransaction $transaction, User $actor): FuelTransaction
    {
        $transaction->update([
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        $this->auditLog->log('fuel_transaction.reviewed', $transaction, $actor);

        return $transaction;
    }
}
