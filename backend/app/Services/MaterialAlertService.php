<?php

namespace App\Services;

use App\Models\MaterialTransaction;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Answers brief question #4 ("Is there any unusual material or diesel
 * consumption?") for the Materials module. A (material, site) pair is
 * flagged for a given day if that day's total ISSUE quantity exceeds the
 * trailing lookback-period daily average by the configured multiplier.
 *
 * Deliberately excludes pairs with no prior history (average == 0) from
 * being flagged as "unusual" on their very first issue — a brand new
 * material/site combination isn't anomalous, it's just new.
 */
class MaterialAlertService
{
    public function highConsumptionAlertsFor(Project $project, string $date): array
    {
        $lookbackDays = config('materials.consumption_lookback_days');
        $multiplier = (string) config('materials.high_consumption_multiplier');

        $targetDate = Carbon::parse($date)->toDateString();
        $lookbackStart = Carbon::parse($date)->subDays($lookbackDays)->toDateString();
        $lookbackEnd = Carbon::parse($date)->subDay()->toDateString();

        $todayTotals = MaterialTransaction::where('project_id', $project->id)
            ->where('transaction_type', 'issue')
            ->whereDate('created_at', $targetDate)
            ->select('material_id', 'site_id', DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('material_id', 'site_id')
            ->get()
            ->keyBy(fn ($row) => $row->material_id.':'.$row->site_id);

        if ($todayTotals->isEmpty()) {
            return [];
        }

        $historicalTotals = MaterialTransaction::where('project_id', $project->id)
            ->where('transaction_type', 'issue')
            ->whereBetween(DB::raw('DATE(created_at)'), [$lookbackStart, $lookbackEnd])
            ->select('material_id', 'site_id', DB::raw('SUM(quantity) as total_quantity'), DB::raw('COUNT(DISTINCT DATE(created_at)) as day_count'))
            ->groupBy('material_id', 'site_id')
            ->get()
            ->keyBy(fn ($row) => $row->material_id.':'.$row->site_id);

        $alerts = [];

        foreach ($todayTotals as $key => $todayRow) {
            $historical = $historicalTotals->get($key);

            if (! $historical || (int) $historical->day_count === 0) {
                continue; // no history — not flagged as "unusual"
            }

            $averageDaily = bcdiv((string) $historical->total_quantity, (string) $historical->day_count, 3);

            if (bccomp($averageDaily, '0', 3) === 0) {
                continue;
            }

            $threshold = bcmul($averageDaily, $multiplier, 3);

            if (bccomp((string) $todayRow->total_quantity, $threshold, 3) > 0) {
                [$materialId, $siteId] = explode(':', $key);

                $alerts[] = [
                    'material_id' => (int) $materialId,
                    'site_id' => (int) $siteId,
                    'date' => $targetDate,
                    'quantity_issued' => (string) $todayRow->total_quantity,
                    'trailing_average_daily' => $averageDaily,
                    'threshold' => $threshold,
                ];
            }
        }

        return $alerts;
    }
}
