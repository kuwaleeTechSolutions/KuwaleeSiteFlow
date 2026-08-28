<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\BulkAttendanceRequest;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Resources\WorkerAttendanceResource;
use App\Models\Site;
use App\Models\WorkerAttendance;
use App\Services\AttendanceService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService)
    {
    }

    /**
     * Lists attendance records. Restricted to sites/projects the caller has
     * access to unless they hold org-wide visibility (mirrors the Daily
     * Report / Project index pattern).
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', WorkerAttendance::class);

        $user = $request->user();

        $records = WorkerAttendance::query()
            ->with(['worker:id,uuid,name,worker_code', 'project:id,uuid', 'site:id,uuid,site_name', 'markedBy:id,uuid,name'])
            ->when(! $user->hasOrgWideVisibility(), function ($query) use ($user) {
                $query->whereHas('site', function ($q) use ($user) {
                    $q->whereHas('assignedUsers', fn ($qq) => $qq->where('users.id', $user->id))
                        ->orWhereHas('project.assignedUsers', fn ($qq) => $qq->where('users.id', $user->id));
                });
            })
            ->when($request->filled('site_id'), fn ($q) => $q->whereHas('site', fn ($qq) => $qq->where('uuid', $request->input('site_id'))))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('attendance_date', $request->input('date')))
            ->when($request->filled('worker_id'), fn ($q) => $q->whereHas('worker', fn ($qq) => $qq->where('uuid', $request->input('worker_id'))))
            ->orderByDesc('attendance_date')
            ->paginate($request->integer('per_page', 50));

        return WorkerAttendanceResource::collection($records)->additional(['success' => true]);
    }

    public function store(StoreAttendanceRequest $request)
    {
        $site = Site::findOrFail($request->validated('site_id'));

        $attendance = $this->attendanceService->markSingle($site, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Attendance recorded successfully.',
            'data' => new WorkerAttendanceResource($attendance->load('worker', 'project', 'site')),
        ], 201);
    }

    public function storeBulk(BulkAttendanceRequest $request)
    {
        $site = Site::findOrFail($request->validated('site_id'));

        $records = $this->attendanceService->markBulk(
            $site,
            $request->validated('attendance_date'),
            $request->input('shift', 'day'),
            $request->validated('entries'),
            $request->user(),
        );

        $collection = (new Collection($records))->load('worker');

        return response()->json([
            'success' => true,
            'message' => count($records).' attendance records created successfully.',
            'data' => WorkerAttendanceResource::collection($collection),
        ], 201);
    }
}
