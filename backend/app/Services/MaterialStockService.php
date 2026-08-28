<?php

namespace App\Services;

use App\Models\Material;
use App\Models\MaterialStock;
use App\Models\MaterialTransaction;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single, authoritative entry point for every stock-changing operation.
 * No controller or other service is permitted to write to `material_stocks`
 * or `material_transactions` directly.
 *
 * Concurrency & integrity guarantees:
 *  - Every mutation runs inside DB::transaction().
 *  - The relevant MaterialStock row(s) are read with lockForUpdate() BEFORE
 *    the balance check, so two concurrent requests against the same
 *    material/project/site serialize instead of racing (brief §31:
 *    "Concurrent stock updates are handled safely").
 *  - Negative stock is rejected by default; only a caller holding
 *    'materials.negative_stock_override' AND explicitly passing
 *    force_override=true can push it through, and that fact is recorded on
 *    the ledger row (is_override) and in the audit log.
 *  - The ledger (material_transactions) is append-only — this service NEVER
 *    updates or deletes an existing transaction row.
 */
class MaterialStockService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function createTransaction(array $data, User $actor): MaterialTransaction
    {
        $material = Material::findOrFail($data['material_id']);
        $site = Site::findOrFail($data['site_id']);

        abort_unless(
            (int) $material->organization_id === (int) $site->organization_id,
            422,
            'Material and site must belong to the same organization.'
        );

        return match ($data['transaction_type']) {
            'transfer' => $this->handleTransfer($material, $site, $data, $actor),
            default => $this->handleSingleLocation($material, $site, $data, $actor),
        };
    }

    private function handleSingleLocation(Material $material, Site $site, array $data, User $actor): MaterialTransaction
    {
        return DB::transaction(function () use ($material, $site, $data, $actor) {
            $stock = $this->lockOrCreateStock($material, $site);

            $decreases = $data['transaction_type'] === 'adjustment'
                ? $data['direction'] === 'decrease'
                : in_array($data['transaction_type'], ['issue'], true);

            $newBalance = $decreases
                ? bcsub((string) $stock->quantity_on_hand, (string) $data['quantity'], 3)
                : bcadd((string) $stock->quantity_on_hand, (string) $data['quantity'], 3);

            $this->assertNonNegative($newBalance, $data, $actor, $material, $site);

            $transaction = MaterialTransaction::create([
                'organization_id' => $material->organization_id,
                'material_id' => $material->id,
                'transaction_type' => $data['transaction_type'],
                'quantity' => $data['quantity'],
                'direction' => $data['transaction_type'] === 'adjustment' ? $data['direction'] : null,
                'project_id' => $site->project_id,
                'site_id' => $site->id,
                'reference_number' => $data['reference_number'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'is_override' => bccomp($newBalance, '0', 3) < 0,
                'created_by' => $actor->id,
            ]);

            $stock->update(['quantity_on_hand' => $newBalance, 'updated_at' => now()]);

            $this->auditLog->log('material_transaction.created', $transaction, $actor, null, [
                'transaction_type' => $transaction->transaction_type,
                'quantity' => (string) $transaction->quantity,
                'resulting_balance' => $newBalance,
                'is_override' => $transaction->is_override,
            ]);

            return $transaction;
        });
    }

    private function handleTransfer(Material $material, Site $fromSite, array $data, User $actor): MaterialTransaction
    {
        $toSite = Site::findOrFail($data['to_site_id']);

        abort_unless(
            (int) $toSite->organization_id === (int) $material->organization_id,
            422,
            'Destination site must belong to the same organization.'
        );

        return DB::transaction(function () use ($material, $fromSite, $toSite, $data, $actor) {
            // Lock BOTH stock rows in a deterministic order (by id) to avoid
            // a classic deadlock where two concurrent transfers lock the
            // same pair of rows in opposite order.
            [$firstSite, $secondSite] = $fromSite->id < $toSite->id
                ? [$fromSite, $toSite]
                : [$toSite, $fromSite];

            $firstStock = $this->lockOrCreateStock($material, $firstSite);
            $secondStock = $this->lockOrCreateStock($material, $secondSite);

            $fromStock = $firstSite->id === $fromSite->id ? $firstStock : $secondStock;
            $toStock = $firstSite->id === $toSite->id ? $firstStock : $secondStock;

            $newFromBalance = bcsub((string) $fromStock->quantity_on_hand, (string) $data['quantity'], 3);
            $this->assertNonNegative($newFromBalance, $data, $actor, $material, $fromSite);

            $newToBalance = bcadd((string) $toStock->quantity_on_hand, (string) $data['quantity'], 3);

            $transaction = MaterialTransaction::create([
                'organization_id' => $material->organization_id,
                'material_id' => $material->id,
                'transaction_type' => 'transfer',
                'quantity' => $data['quantity'],
                'project_id' => $fromSite->project_id,
                'site_id' => $fromSite->id,
                'to_site_id' => $toSite->id,
                'reference_number' => $data['reference_number'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'is_override' => bccomp($newFromBalance, '0', 3) < 0,
                'created_by' => $actor->id,
            ]);

            $fromStock->update(['quantity_on_hand' => $newFromBalance, 'updated_at' => now()]);
            $toStock->update(['quantity_on_hand' => $newToBalance, 'updated_at' => now()]);

            $this->auditLog->log('material_transaction.created', $transaction, $actor, null, [
                'transaction_type' => 'transfer',
                'quantity' => (string) $transaction->quantity,
                'from_site_id' => $fromSite->id,
                'to_site_id' => $toSite->id,
                'is_override' => $transaction->is_override,
            ]);

            return $transaction;
        });
    }

    private function lockOrCreateStock(Material $material, Site $site): MaterialStock
    {
        $stock = MaterialStock::where('material_id', $material->id)
            ->where('project_id', $site->project_id)
            ->where('site_id', $site->id)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            return $stock;
        }

        // Not found: create the zero-balance row. A concurrent request
        // racing to create the same row will hit the unique constraint on
        // (material_id, project_id, site_id); we catch that and re-select
        // with a lock rather than erroring the whole operation.
        try {
            return MaterialStock::create([
                'organization_id' => $material->organization_id,
                'material_id' => $material->id,
                'project_id' => $site->project_id,
                'site_id' => $site->id,
                'quantity_on_hand' => 0,
                'updated_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return MaterialStock::where('material_id', $material->id)
                ->where('project_id', $site->project_id)
                ->where('site_id', $site->id)
                ->lockForUpdate()
                ->firstOrFail();
        }
    }

    private function assertNonNegative(string $newBalance, array $data, User $actor, Material $material, Site $site): void
    {
        if (bccomp($newBalance, '0', 3) >= 0) {
            return;
        }

        $forceOverride = (bool) ($data['force_override'] ?? false);

        if (! $forceOverride || ! $actor->hasPermission('materials.negative_stock_override')) {
            throw ValidationException::withMessages([
                'quantity' => ['This transaction would result in negative stock for '.$material->material_name.' at '.$site->site_name.'. Set force_override=true with the required permission to proceed anyway.'],
            ]);
        }
    }
}
