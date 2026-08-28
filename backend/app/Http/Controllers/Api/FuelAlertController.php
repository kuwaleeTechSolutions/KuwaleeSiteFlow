<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Project;
use App\Services\FuelAlertService;
use Illuminate\Http\Request;

class FuelAlertController extends Controller
{
    public function __construct(private readonly FuelAlertService $alertService)
    {
    }

    public function index(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $date = $request->input('date', now()->toDateString());

        $missingReadings = $this->alertService->missingMeterReadingAlertsFor($project, $date);
        $highConsumption = $this->enrichWithEquipment($this->alertService->highConsumptionAlertsFor($project, $date));
        $aboveThreshold = $this->enrichWithEquipment($this->alertService->aboveThresholdAlertsFor($project, $date));

        return response()->json([
            'success' => true,
            'data' => [
                'missing_meter_reading' => $missingReadings,
                'high_consumption' => $highConsumption,
                'above_configured_threshold' => $aboveThreshold,
            ],
        ]);
    }

    private function enrichWithEquipment(array $alerts): array
    {
        return collect($alerts)->map(function (array $alert) {
            $equipment = Equipment::withoutGlobalScopes()->find($alert['equipment_id']);
            $alert['equipment'] = $equipment?->only(['equipment_code', 'equipment_name']);

            return $alert;
        })->values()->all();
    }
}
