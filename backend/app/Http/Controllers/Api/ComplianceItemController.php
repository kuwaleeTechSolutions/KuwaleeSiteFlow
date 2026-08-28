<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Compliance\StoreComplianceItemRequest;
use App\Http\Requests\Compliance\UpdateComplianceItemRequest;
use App\Http\Resources\ComplianceItemResource;
use App\Models\ComplianceItem;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class ComplianceItemController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', ComplianceItem::class);

        $items = ComplianceItem::query()
            ->with('responsiblePerson', 'document')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->orderBy('expiry_date')
            ->paginate($request->integer('per_page', 20));

        return ComplianceItemResource::collection($items)->additional(['success' => true]);
    }

    public function show(ComplianceItem $complianceItem)
    {
        $this->authorize('view', $complianceItem);

        return response()->json([
            'success' => true,
            'data' => new ComplianceItemResource($complianceItem->load('responsiblePerson', 'document')),
        ]);
    }

    public function store(StoreComplianceItemRequest $request)
    {
        $item = ComplianceItem::create(array_merge($request->validated(), [
            'organization_id' => $request->user()->organization_id,
            'created_by' => $request->user()->id,
        ]));

        $this->auditLog->log('compliance_item.created', $item, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Compliance item created successfully.',
            'data' => new ComplianceItemResource($item),
        ], 201);
    }

    public function update(UpdateComplianceItemRequest $request, ComplianceItem $complianceItem)
    {
        $oldValues = $complianceItem->only(['title', 'expiry_date', 'responsible_person_id']);

        // If the expiry_date is being extended, reset the alert-threshold
        // tracking so a previously-fired 7-day alert doesn't suppress a
        // legitimate new 60/30-day alert cycle for the updated date.
        $complianceItem->update(array_merge($request->validated(), [
            'last_alert_threshold_days' => null,
            'status' => 'valid',
        ]));

        $this->auditLog->log('compliance_item.updated', $complianceItem, $request->user(), $oldValues, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Compliance item updated successfully.',
            'data' => new ComplianceItemResource($complianceItem->fresh()),
        ]);
    }

    public function destroy(Request $request, ComplianceItem $complianceItem)
    {
        $this->authorize('delete', $complianceItem);

        $complianceItem->delete();

        $this->auditLog->log('compliance_item.deleted', $complianceItem, $request->user());

        return response()->json(['success' => true, 'message' => 'Compliance item deleted successfully.']);
    }
}
