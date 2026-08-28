<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Measurement\RejectMeasurementRequest;
use App\Http\Requests\Measurement\StoreMeasurementRequest;
use App\Http\Resources\MeasurementResource;
use App\Models\Measurement;
use App\Models\Site;
use App\Services\MeasurementService;
use Illuminate\Http\Request;

class MeasurementController extends Controller
{
    public function __construct(private readonly MeasurementService $measurementService)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Measurement::class);

        $user = $request->user();

        $measurements = Measurement::query()
            ->with(['project:id,uuid', 'site:id,uuid,site_name', 'creator:id,uuid,name'])
            ->when(! $user->hasOrgWideVisibility(), function ($query) use ($user) {
                $query->whereHas('site', function ($q) use ($user) {
                    $q->whereHas('assignedUsers', fn ($qq) => $qq->where('users.id', $user->id))
                        ->orWhereHas('project.assignedUsers', fn ($qq) => $qq->where('users.id', $user->id));
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('project_id'), fn ($q) => $q->whereHas('project', fn ($qq) => $qq->where('uuid', $request->input('project_id'))))
            ->orderByDesc('measurement_date')
            ->paginate($request->integer('per_page', 20));

        return MeasurementResource::collection($measurements)->additional(['success' => true]);
    }

    public function show(Measurement $measurement)
    {
        $this->authorize('view', $measurement);

        return response()->json([
            'success' => true,
            'data' => new MeasurementResource($measurement->load(
                'project', 'site', 'creator', 'approver', 'items.boqItem'
            )),
        ]);
    }

    public function store(StoreMeasurementRequest $request)
    {
        $site = Site::findOrFail($request->validated('site_id'));

        $measurement = $this->measurementService->create($site, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Measurement saved as draft.',
            'data' => new MeasurementResource($measurement->load('project', 'site', 'items.boqItem')),
        ], 201);
    }

    public function submit(Request $request, Measurement $measurement)
    {
        $this->authorize('submit', $measurement);

        $this->measurementService->submit($measurement, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Measurement submitted for approval.',
            'data' => new MeasurementResource($measurement->fresh()),
        ]);
    }

    public function approve(Request $request, Measurement $measurement)
    {
        $this->authorize('approve', $measurement);

        $this->measurementService->approve($measurement, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Measurement approved.',
            'data' => new MeasurementResource($measurement->fresh(['approver'])),
        ]);
    }

    public function reject(RejectMeasurementRequest $request, Measurement $measurement)
    {
        $this->measurementService->reject($measurement, $request->user(), $request->validated('review_remarks'));

        return response()->json([
            'success' => true,
            'message' => 'Measurement rejected.',
            'data' => new MeasurementResource($measurement->fresh(['approver'])),
        ]);
    }
}
