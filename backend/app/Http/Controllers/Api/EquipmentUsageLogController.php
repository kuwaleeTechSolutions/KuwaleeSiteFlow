<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Equipment\StoreEquipmentUsageLogRequest;
use App\Http\Resources\EquipmentUsageLogResource;
use App\Models\EquipmentUsageLog;
use App\Models\Equipment;
use App\Models\Site;
use App\Models\User;
use App\Services\EquipmentUsageService;
use Illuminate\Http\Request;

class EquipmentUsageLogController extends Controller
{
    public function __construct(private readonly EquipmentUsageService $usageService)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', EquipmentUsageLog::class);

        $user = $request->user();

        $logs = EquipmentUsageLog::query()
            ->with(['equipment:id,uuid,equipment_name', 'project:id,uuid', 'site:id,uuid,site_name', 'operator:id,uuid,name'])
            ->when(! $user->hasOrgWideVisibility(), function ($query) use ($user) {
                $query->whereHas('site', function ($q) use ($user) {
                    $q->whereHas('assignedUsers', fn ($qq) => $qq->where('users.id', $user->id))
                        ->orWhereHas('project.assignedUsers', fn ($qq) => $qq->where('users.id', $user->id));
                });
            })
            ->when($request->filled('equipment_id'), fn ($q) => $q->whereHas('equipment', fn ($qq) => $qq->where('uuid', $request->input('equipment_id'))))
            ->when($request->filled('project_id'), fn ($q) => $q->whereHas('project', fn ($qq) => $qq->where('uuid', $request->input('project_id'))))
            ->when($request->filled('site_id'), fn ($q) => $q->whereHas('site', fn ($qq) => $qq->where('uuid', $request->input('site_id'))))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('usage_date', $request->input('date')))
            ->orderByDesc('usage_date')
            ->paginate($request->integer('per_page', 50));

        return EquipmentUsageLogResource::collection($logs)->additional(['success' => true]);
    }

    public function store(StoreEquipmentUsageLogRequest $request)
    {
        $data = $request->validated();
        $site = Site::where('uuid', $data['site_id'])->firstOrFail();
        $data['equipment_id'] = Equipment::where('uuid', $data['equipment_id'])->valueOrFail('id');
        if (! empty($data['operator_id'])) {
            $data['operator_id'] = User::where('uuid', $data['operator_id'])->valueOrFail('id');
        }

        $log = $this->usageService->logUsage($site, $data, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Equipment usage logged successfully.',
            'data' => new EquipmentUsageLogResource($log->load('equipment', 'project', 'site', 'operator')),
        ], 201);
    }
}
