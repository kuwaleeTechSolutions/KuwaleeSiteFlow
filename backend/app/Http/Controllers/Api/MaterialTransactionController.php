<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Material\StoreMaterialTransactionRequest;
use App\Http\Resources\MaterialTransactionResource;
use App\Models\MaterialTransaction;
use App\Models\Material;
use App\Models\Site;
use App\Services\MaterialStockService;
use Illuminate\Http\Request;

class MaterialTransactionController extends Controller
{
    public function __construct(private readonly MaterialStockService $stockService)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', MaterialTransaction::class);

        $user = $request->user();

        $transactions = MaterialTransaction::query()
            ->with(['material:id,uuid,material_name,unit', 'project:id,uuid', 'site:id,uuid,site_name', 'toSite:id,uuid,site_name', 'creator:id,uuid,name'])
            ->when(! $user->hasOrgWideVisibility(), function ($query) use ($user) {
                $query->whereHas('site', function ($q) use ($user) {
                    $q->whereHas('assignedUsers', fn ($qq) => $qq->where('users.id', $user->id))
                        ->orWhereHas('project.assignedUsers', fn ($qq) => $qq->where('users.id', $user->id));
                });
            })
            ->when($request->filled('material_id'), fn ($q) => $q->whereHas('material', fn ($qq) => $qq->where('uuid', $request->input('material_id'))))
            ->when($request->filled('site_id'), fn ($q) => $q->whereHas('site', fn ($qq) => $qq->where('uuid', $request->input('site_id'))))
            ->when($request->filled('project_id'), fn ($q) => $q->whereHas('project', fn ($qq) => $qq->where('uuid', $request->input('project_id'))))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('created_at', $request->input('date')))
            ->when($request->filled('transaction_type'), fn ($q) => $q->where('transaction_type', $request->input('transaction_type')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 50));

        return MaterialTransactionResource::collection($transactions)->additional(['success' => true]);
    }

    public function show(MaterialTransaction $materialTransaction)
    {
        $this->authorize('view', $materialTransaction);

        return response()->json([
            'success' => true,
            'data' => new MaterialTransactionResource($materialTransaction->load('material', 'project', 'site', 'toSite', 'creator')),
        ]);
    }

    public function store(StoreMaterialTransactionRequest $request)
    {
        $data = $request->validated();
        $data['material_id'] = Material::where('uuid', $data['material_id'])->valueOrFail('id');
        $data['site_id'] = Site::where('uuid', $data['site_id'])->valueOrFail('id');
        if (! empty($data['to_site_id'])) {
            $data['to_site_id'] = Site::where('uuid', $data['to_site_id'])->valueOrFail('id');
        }
        $transaction = $this->stockService->createTransaction($data, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Material transaction recorded successfully.',
            'data' => new MaterialTransactionResource($transaction->load('material', 'project', 'site', 'toSite')),
        ], 201);
    }
}
