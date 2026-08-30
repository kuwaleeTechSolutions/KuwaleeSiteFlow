<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Measurement\RejectMeasurementRequest;
use App\Http\Requests\Measurement\StoreMeasurementRequest;
use App\Http\Resources\MeasurementResource;
use App\Models\Measurement;
use App\Models\BoqItem;
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
            ->when($request->filled('site_id'), fn ($q) => $q->whereHas('site', fn ($qq) => $qq->where('uuid', $request->input('site_id'))))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('measurement_date', $request->input('date')))
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
        $data = $request->validated();
        $site = Site::where('uuid', $data['site_id'])->firstOrFail();
        $data['site_id'] = $site->id;
        $data['items'] = collect($data['items'])->map(function (array $item) {
            $item['boq_item_id'] = BoqItem::where('uuid', $item['boq_item_id'])->valueOrFail('id');
            return $item;
        })->all();
        if (! empty($data['revises_measurement_id'])) {
            $data['revises_measurement_id'] = Measurement::where('uuid', $data['revises_measurement_id'])->valueOrFail('id');
        }

        $measurement = $this->measurementService->create($site, $data, $request->user());

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
