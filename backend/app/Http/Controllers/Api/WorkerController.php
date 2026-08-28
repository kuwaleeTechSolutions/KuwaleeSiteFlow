<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Worker\StoreWorkerRequest;
use App\Http\Requests\Worker\UpdateWorkerRequest;
use App\Http\Resources\WorkerResource;
use App\Models\Worker;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class WorkerController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Worker::class);

        $workers = Worker::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->input('search').'%')
                    ->orWhere('worker_code', 'like', '%'.$request->input('search').'%');
            }))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return WorkerResource::collection($workers)->additional(['success' => true]);
    }

    public function show(Worker $worker)
    {
        $this->authorize('view', $worker);

        return response()->json(['success' => true, 'data' => new WorkerResource($worker)]);
    }

    public function store(StoreWorkerRequest $request)
    {
        $worker = Worker::create($request->validated());

        $this->auditLog->log('worker.created', $worker, $request->user(), null, [
            'worker_code' => $worker->worker_code, 'name' => $worker->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Worker created successfully.',
            'data' => new WorkerResource($worker),
        ], 201);
    }

    public function update(UpdateWorkerRequest $request, Worker $worker)
    {
        // daily_wage changes are audited explicitly (even though redacted
        // from the API response for unauthorized viewers, the change
        // itself must still be traceable to whoever made it).
        $oldValues = $worker->only(['name', 'daily_wage', 'status']);

        $worker->update($request->validated());

        $this->auditLog->log('worker.updated', $worker, $request->user(), $oldValues, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Worker updated successfully.',
            'data' => new WorkerResource($worker->fresh()),
        ]);
    }
}
