<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Project;
use App\Models\Site;
use App\Services\MaterialAlertService;
use Illuminate\Http\Request;

class MaterialAlertController extends Controller
{
    public function __construct(private readonly MaterialAlertService $alertService)
    {
    }

    public function highConsumption(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $date = $request->input('date', now()->toDateString());
        $alerts = $this->alertService->highConsumptionAlertsFor($project, $date);

        $enriched = collect($alerts)->map(function (array $alert) {
            return array_merge($alert, [
                'material' => Material::withoutGlobalScopes()->find($alert['material_id'])?->only(['material_name', 'unit']),
                'site' => Site::withoutGlobalScopes()->find($alert['site_id'])?->only(['site_name']),
            ]);
        })->values();

        return response()->json(['success' => true, 'data' => $enriched]);
    }
}
