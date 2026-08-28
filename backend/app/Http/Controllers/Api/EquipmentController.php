<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Equipment\StoreEquipmentRequest;
use App\Http\Requests\Equipment\UpdateEquipmentRequest;
use App\Http\Resources\EquipmentResource;
use App\Models\Equipment;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class EquipmentController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Equipment::class);

        $equipment = Equipment::query()
            ->with('assignedProject', 'assignedSite', 'currentOperator')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('equipment_name', 'like', '%'.$request->input('search').'%')
                    ->orWhere('equipment_code', 'like', '%'.$request->input('search').'%');
            }))
            ->orderBy('equipment_name')
            ->paginate($request->integer('per_page', 20));

        return EquipmentResource::collection($equipment)->additional(['success' => true]);
    }

    public function show(Equipment $equipment)
    {
        $this->authorize('view', $equipment);

        return response()->json([
            'success' => true,
            'data' => new EquipmentResource($equipment->load('assignedProject', 'assignedSite', 'currentOperator')),
        ]);
    }

    public function store(StoreEquipmentRequest $request)
    {
        $equipment = Equipment::create($request->validated());

        $this->auditLog->log('equipment.created', $equipment, $request->user(), null, $equipment->only(['equipment_code', 'equipment_name']));

        return response()->json([
            'success' => true,
            'message' => 'Equipment created successfully.',
            'data' => new EquipmentResource($equipment),
        ], 201);
    }

    public function update(UpdateEquipmentRequest $request, Equipment $equipment)
    {
        $oldValues = $equipment->only(['status', 'assigned_project_id', 'assigned_site_id', 'current_operator_id']);

        $equipment->update($request->validated());

        $this->auditLog->log('equipment.updated', $equipment, $request->user(), $oldValues, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Equipment updated successfully.',
            'data' => new EquipmentResource($equipment->fresh(['assignedProject', 'assignedSite', 'currentOperator'])),
        ]);
    }

    public function destroy(Request $request, Equipment $equipment)
    {
        $this->authorize('delete', $equipment);

        $equipment->delete();

        $this->auditLog->log('equipment.deleted', $equipment, $request->user());

        return response()->json(['success' => true, 'message' => 'Equipment deleted successfully.']);
    }
}
