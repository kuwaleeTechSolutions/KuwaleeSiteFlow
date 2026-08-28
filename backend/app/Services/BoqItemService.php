<?php

namespace App\Services;

use App\Models\BoqItem;
use App\Models\BoqRevision;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Owns all BOQ revisioning logic. A "revision" NEVER edits existing
 * boq_items rows — it creates a new boq_revisions row and new boq_items
 * rows for the item_numbers being introduced/changed. Item_numbers not
 * included in a new revision simply remain visible via their most recent
 * prior revision (see currentItemsForProject()).
 */
class BoqItemService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function createRevision(Project $project, array $data, User $actor): BoqRevision
    {
        return DB::transaction(function () use ($project, $data, $actor) {
            $nextRevisionNumber = (int) (BoqRevision::where('project_id', $project->id)->max('revision_number') ?? 0) + 1;

            $revision = BoqRevision::create([
                'organization_id' => $project->organization_id,
                'project_id' => $project->id,
                'revision_number' => $nextRevisionNumber,
                'reason' => $data['reason'],
                'effective_date' => $data['effective_date'],
                'created_by' => $actor->id,
            ]);

            foreach ($data['items'] as $itemData) {
                $contractValue = bcmul((string) $itemData['contract_quantity'], (string) $itemData['contract_rate'], 2);

                BoqItem::create([
                    'organization_id' => $project->organization_id,
                    'project_id' => $project->id,
                    'boq_revision_id' => $revision->id,
                    'item_number' => $itemData['item_number'],
                    'description' => $itemData['description'],
                    'unit' => $itemData['unit'],
                    'contract_quantity' => $itemData['contract_quantity'],
                    'contract_rate' => $itemData['contract_rate'],
                    'contract_value' => $contractValue,
                ]);
            }

            $this->auditLog->log('boq_revision.created', $revision, $actor, null, [
                'revision_number' => $nextRevisionNumber,
                'item_count' => count($data['items']),
            ]);

            return $revision;
        });
    }

    /**
     * Returns the "current effective" BOQ — for each item_number, the row
     * belonging to its most recent revision. Implemented as a portable
     * two-step lookup (works identically on MySQL and the SQLite test
     * driver) rather than a single window-function query.
     *
     * PERFORMANCE NOTE: this issues one query per distinct item_number
     * after the initial grouping query. Acceptable for typical BOQ sizes
     * (tens to low hundreds of line items); if a future customer's BOQ
     * grows into the thousands, this should be replaced with a single
     * window-function query (ROW_NUMBER() OVER (PARTITION BY item_number
     * ORDER BY revision_number DESC)) once MySQL 8+ is guaranteed in
     * production (it already supports this; the SQLite test driver does
     * too, so this is a safe future optimization).
     */
    public function currentItemsForProject(Project $project): Collection
    {
        $latestRevisionPerItem = DB::table('boq_items as bi')
            ->join('boq_revisions as br', 'bi.boq_revision_id', '=', 'br.id')
            ->where('bi.project_id', $project->id)
            ->select('bi.item_number', DB::raw('MAX(br.revision_number) as max_revision'))
            ->groupBy('bi.item_number')
            ->get();

        $ids = [];
        foreach ($latestRevisionPerItem as $row) {
            $id = DB::table('boq_items as bi')
                ->join('boq_revisions as br', 'bi.boq_revision_id', '=', 'br.id')
                ->where('bi.project_id', $project->id)
                ->where('bi.item_number', $row->item_number)
                ->where('br.revision_number', $row->max_revision)
                ->value('bi.id');

            if ($id) {
                $ids[] = $id;
            }
        }

        return BoqItem::whereIn('id', $ids)->orderBy('item_number')->get();
    }

    /**
     * All boq_items ids that have ever shared this item_number within the
     * project — used to aggregate measured/billed quantities across
     * revisions (a measurement recorded against an older revision's row
     * still counts toward the item's overall progress).
     */
    public function allItemIdsForItemNumber(Project $project, string $itemNumber): array
    {
        return BoqItem::where('project_id', $project->id)
            ->where('item_number', $itemNumber)
            ->pluck('id')
            ->all();
    }
}
