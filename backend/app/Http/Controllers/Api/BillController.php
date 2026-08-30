<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bill\StoreBillRequest;
use App\Http\Resources\BillResource;
use App\Models\Bill;
use App\Models\Project;
use App\Services\BillingService;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function __construct(private readonly BillingService $billingService)
    {
    }

    public function index(Request $request, Project $project)
    {
        $this->authorize('viewAny', Bill::class);
        abort_unless($request->user()->hasOrgWideVisibility() || $project->isUserAssigned($request->user()->id), 403);

        $bills = $project->bills()
            ->with('creator', 'certifier')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('bill_date', $request->input('date')))
            ->orderByDesc('bill_date')
            ->paginate($request->integer('per_page', 20));

        return BillResource::collection($bills)->additional(['success' => true]);
    }

    public function show(Bill $bill)
    {
        $this->authorize('view', $bill);

        return response()->json([
            'success' => true,
            'data' => new BillResource($bill->load('project', 'creator', 'certifier', 'items.boqItem')),
        ]);
    }

    public function store(StoreBillRequest $request, Project $project)
    {
        $bill = $this->billingService->create($project, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Bill created successfully.',
            'data' => new BillResource($bill->load('items.boqItem')),
        ], 201);
    }

    public function submit(Request $request, Bill $bill)
    {
        $this->authorize('update', $bill);

        $this->billingService->submit($bill, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Bill submitted for certification.',
            'data' => new BillResource($bill->fresh()),
        ]);
    }

    public function certify(Request $request, Bill $bill)
    {
        $this->authorize('certify', $bill);

        $this->billingService->certify($bill, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Bill certified.',
            'data' => new BillResource($bill->fresh(['certifier'])),
        ]);
    }
}
