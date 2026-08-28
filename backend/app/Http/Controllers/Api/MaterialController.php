<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Material\StoreMaterialRequest;
use App\Http\Requests\Material\UpdateMaterialRequest;
use App\Http\Resources\MaterialResource;
use App\Models\Material;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Material::class);

        $materials = Material::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('material_name', 'like', '%'.$request->input('search').'%')
                    ->orWhere('material_code', 'like', '%'.$request->input('search').'%');
            }))
            ->orderBy('material_name')
            ->paginate($request->integer('per_page', 20));

        return MaterialResource::collection($materials)->additional(['success' => true]);
    }

    public function show(Material $material)
    {
        $this->authorize('view', $material);

        return response()->json(['success' => true, 'data' => new MaterialResource($material)]);
    }

    public function store(StoreMaterialRequest $request)
    {
        $material = Material::create($request->validated());

        $this->auditLog->log('material.created', $material, $request->user(), null, $material->only(['material_code', 'material_name']));

        return response()->json([
            'success' => true,
            'message' => 'Material created successfully.',
            'data' => new MaterialResource($material),
        ], 201);
    }

    public function update(UpdateMaterialRequest $request, Material $material)
    {
        $oldValues = $material->only(['material_name', 'unit', 'minimum_stock', 'status']);

        $material->update($request->validated());

        $this->auditLog->log('material.updated', $material, $request->user(), $oldValues, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Material updated successfully.',
            'data' => new MaterialResource($material->fresh()),
        ]);
    }
}
