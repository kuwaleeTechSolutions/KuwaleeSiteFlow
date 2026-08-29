<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fuel\StoreFuelTransactionRequest;
use App\Http\Requests\Fuel\UpdateFuelTransactionRequest;
use App\Http\Resources\FuelTransactionResource;
use App\Models\FuelTransaction;
use App\Models\Equipment;
use App\Models\Site;
use App\Services\FuelTransactionService;
use Illuminate\Http\Request;

class FuelTransactionController extends Controller
{
    public function __construct(private readonly FuelTransactionService $fuelService)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', FuelTransaction::class);

        $user = $request->user();

        $transactions = FuelTransaction::query()
            ->with(['equipment:id,uuid,equipment_name', 'project:id,uuid', 'site:id,uuid,site_name', 'recorder:id,uuid,name', 'reviewer:id,uuid,name'])
            ->when(! $user->hasOrgWideVisibility(), function ($query) use ($user) {
                $query->whereHas('site', function ($q) use ($user) {
                    $q->whereHas('assignedUsers', fn ($qq) => $qq->where('users.id', $user->id))
                        ->orWhereHas('project.assignedUsers', fn ($qq) => $qq->where('users.id', $user->id));
                });
            })
            ->when($request->filled('equipment_id'), fn ($q) => $q->whereHas('equipment', fn ($qq) => $qq->where('uuid', $request->input('equipment_id'))))
            ->when($request->filled('transaction_type'), fn ($q) => $q->where('transaction_type', $request->input('transaction_type')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 50));

        return FuelTransactionResource::collection($transactions)->additional(['success' => true]);
    }

    public function show(FuelTransaction $fuelTransaction)
    {
        $this->authorize('view', $fuelTransaction);

        return response()->json([
            'success' => true,
            'data' => new FuelTransactionResource($fuelTransaction->load('equipment', 'project', 'site', 'recorder', 'reviewer')),
        ]);
    }

    public function store(StoreFuelTransactionRequest $request)
    {
        $data = $request->validated();
        $site = Site::where('uuid', $data['site_id'])->firstOrFail();
        if (! empty($data['equipment_id'])) {
            $data['equipment_id'] = Equipment::where('uuid', $data['equipment_id'])->valueOrFail('id');
        }

        $transaction = $this->fuelService->record($site, $data, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Fuel transaction recorded successfully.',
            'data' => new FuelTransactionResource($transaction->load('equipment', 'project', 'site')),
        ], 201);
    }

    public function update(UpdateFuelTransactionRequest $request, FuelTransaction $fuelTransaction)
    {
        $this->fuelService->update($fuelTransaction, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Fuel transaction updated successfully.',
            'data' => new FuelTransactionResource($fuelTransaction->fresh(['equipment', 'project', 'site'])),
        ]);
    }

    public function review(Request $request, FuelTransaction $fuelTransaction)
    {
        $this->authorize('review', $fuelTransaction);

        $this->fuelService->review($fuelTransaction, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Fuel transaction reviewed successfully.',
            'data' => new FuelTransactionResource($fuelTransaction->fresh(['reviewer'])),
        ]);
    }
}
