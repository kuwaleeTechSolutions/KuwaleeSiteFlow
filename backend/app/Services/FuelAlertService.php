<?php

namespace App\Services;

use App\Models\FuelTransaction;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Answers brief question #4 ("unusual material or diesel consumption?") for
 * the Fuel module, plus the specific alert types listed in brief §17:
 * High fuel consumption, Missing meter reading, Consumption above a
 * configured threshold. ("Unexpected fuel usage" is treated as covered by
 * the combination of the above three, rather than a fourth distinct metric.)
 */
class FuelAlertService
{
    public function missingMeterReadingAlertsFor(Project $project, string $date): array
    {
        return FuelTransaction::where('project_id', $project->id)
            ->where('transaction_type', 'issue')
            ->whereDate('created_at', Carbon::parse($date)->toDateString())
            ->where(fn ($q) => $q->whereNull('opening_reading')->orWhereNull('closing_reading'))
            ->get()
            ->map(fn (FuelTransaction $t) => [
                'fuel_transaction_id' => $t->uuid,
                'equipment_id' => $t->equipment_id,
                'site_id' => $t->site_id,
                'quantity' => (string) $t->quantity,
            ])
            ->values()
            ->all();
    }

    public function highConsumptionAlertsFor(Project $project, string $date): array
    {
        $lookbackDays = config('fuel.consumption_lookback_days');
        $multiplier = (string) config('fuel.high_consumption_multiplier');
        $targetDate = Carbon::parse($date)->toDateString();
        $lookbackStart = Carbon::parse($date)->subDays($lookbackDays)->toDateString();
        $lookbackEnd = Carbon::parse($date)->subDay()->toDateString();

        $todayTotals = FuelTransaction::where('project_id', $project->id)
            ->where('transaction_type', 'issue')
            ->whereNotNull('equipment_id')
            ->whereDate('created_at', $targetDate)
            ->select('equipment_id', DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('equipment_id')
            ->get()
            ->keyBy('equipment_id');

        if ($todayTotals->isEmpty()) {
            return [];
        }

        $historicalTotals = FuelTransaction::where('project_id', $project->id)
            ->where('transaction_type', 'issue')
            ->whereNotNull('equipment_id')
            ->whereBetween(DB::raw('DATE(created_at)'), [$lookbackStart, $lookbackEnd])
            ->select('equipment_id', DB::raw('SUM(quantity) as total_quantity'), DB::raw('COUNT(DISTINCT DATE(created_at)) as day_count'))
            ->groupBy('equipment_id')
            ->get()
            ->keyBy('equipment_id');

        $alerts = [];

        foreach ($todayTotals as $equipmentId => $todayRow) {
            $historical = $historicalTotals->get($equipmentId);

            if (! $historical || (int) $historical->day_count === 0) {
                continue; // no history — not "unusual" yet
            }

            $averageDaily = bcdiv((string) $historical->total_quantity, (string) $historical->day_count, 3);

            if (bccomp($averageDaily, '0', 3) === 0) {
                continue;
            }

            $threshold = bcmul($averageDaily, $multiplier, 3);

            if (bccomp((string) $todayRow->total_quantity, $threshold, 3) > 0) {
                $alerts[] = [
                    'equipment_id' => (int) $equipmentId,
                    'date' => $targetDate,
                    'quantity_issued' => (string) $todayRow->total_quantity,
                    'trailing_average_daily' => $averageDaily,
                    'threshold' => $threshold,
                ];
            }
        }

        return $alerts;
    }

    public function aboveThresholdAlertsFor(Project $project, string $date): array
    {
        $maxDaily = $project->organization?->setting('fuel_max_daily_quantity', config('fuel.default_max_daily_quantity'));

        if ($maxDaily === null) {
            return []; // no threshold configured for this organization
        }

        return FuelTransaction::where('project_id', $project->id)
            ->where('transaction_type', 'issue')
            ->whereNotNull('equipment_id')
            ->whereDate('created_at', Carbon::parse($date)->toDateString())
            ->select('equipment_id', DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('equipment_id')
            ->get()
            ->filter(fn ($row) => bccomp((string) $row->total_quantity, (string) $maxDaily, 3) > 0)
            ->map(fn ($row) => [
                'equipment_id' => (int) $row->equipment_id,
                'quantity_issued' => (string) $row->total_quantity,
                'configured_threshold' => (string) $maxDaily,
            ])
            ->values()
            ->all();
    }
}
