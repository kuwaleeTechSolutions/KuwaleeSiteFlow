<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DailyReport\ApproveDailyReportRequest;
use App\Http\Requests\DailyReport\ReturnDailyReportRequest;
use App\Http\Requests\DailyReport\StoreDailyReportRequest;
use App\Http\Requests\DailyReport\UpdateDailyReportRequest;
use App\Http\Resources\DailyReportResource;
use App\Models\DailyReport;
use App\Models\Site;
use App\Services\AuditLogService;
use App\Services\DailyReportWorkflowService;
use Illuminate\Http\Request;

class DailyReportController extends Controller
{
    public function __construct(
        private readonly DailyReportWorkflowService $workflow,
        private readonly AuditLogService $auditLog,
    ) {
    }

    /**
     * Lists reports. OrganizationScope already restricts to the caller's
     * org; additionally restrict to reports on sites/projects the user has
     * access to unless they hold org-wide visibility — mirrors the Project
     * index behaviour so a Site Supervisor never even sees another site's
     * reports in a list.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', DailyReport::class);

        $user = $request->user();

        $reports = DailyReport::query()
            ->with(['project:id,uuid,project_name', 'site:id,uuid,site_name', 'creator:id,uuid,name'])
            ->when(! $user->hasOrgWideVisibility(), function ($query) use ($user) {
                $query->whereHas('site', function ($q) use ($user) {
                    $q->whereHas('assignedUsers', fn ($qq) => $qq->where('users.id', $user->id))
                        ->orWhereHas('project.assignedUsers', fn ($qq) => $qq->where('users.id', $user->id));
                });
            })
            ->when($request->filled('project_id'), fn ($q) => $q->whereHas('project', fn ($qq) => $qq->where('uuid', $request->input('project_id'))))
            ->when($request->filled('site_id'), fn ($q) => $q->whereHas('site', fn ($qq) => $qq->where('uuid', $request->input('site_id'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('report_date', $request->input('date')))
            ->orderByDesc('report_date')
            ->paginate($request->integer('per_page', 20));

        return DailyReportResource::collection($reports)->additional(['success' => true]);
    }

    public function show(DailyReport $dailyReport)
    {
        $this->authorize('view', $dailyReport);

        return response()->json([
            'success' => true,
            'data' => new DailyReportResource($dailyReport->load(
                'project', 'site', 'creator', 'submitter', 'reviewer', 'photos'
            )),
        ]);
    }

    public function store(StoreDailyReportRequest $request)
    {
        $site = Site::where('uuid', $request->validated('site_id'))->firstOrFail();
        $report = DailyReport::create([
        ...$request->safe()->except('site_id'),
        'site_id' => $site->id,
        'project_id' => $site->project_id,
        'created_by' => $request->user()->id,
        ]);

        $this->auditLog->log('daily_report.created', $report, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Daily report saved as draft.',
            'data' => new DailyReportResource($report->load('project', 'site')),
        ], 201);
    }

    public function update(UpdateDailyReportRequest $request, DailyReport $dailyReport)
    {
        $oldValues = $dailyReport->only(['work_activities', 'work_completed', 'quantity_completed']);

        $dailyReport->update($request->validated());

        $this->auditLog->log('daily_report.updated', $dailyReport, $request->user(), $oldValues, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Daily report updated.',
            'data' => new DailyReportResource($dailyReport->fresh(['project', 'site'])),
        ]);
    }

    public function submit(Request $request, DailyReport $dailyReport)
    {
        $this->authorize('submit', $dailyReport);

        $this->workflow->submit($dailyReport, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Daily report submitted for review.',
            'data' => new DailyReportResource($dailyReport->fresh()),
        ]);
    }

    public function approve(ApproveDailyReportRequest $request, DailyReport $dailyReport)
    {
        $this->workflow->approve($dailyReport, $request->user(), $request->validated('review_remarks'));

        return response()->json([
            'success' => true,
            'message' => 'Daily report approved.',
            'data' => new DailyReportResource($dailyReport->fresh(['reviewer'])),
        ]);
    }

    public function returnForCorrection(ReturnDailyReportRequest $request, DailyReport $dailyReport)
    {
        $this->workflow->returnForCorrection($dailyReport, $request->user(), $request->validated('review_remarks'));

        return response()->json([
            'success' => true,
            'message' => 'Daily report returned for correction.',
            'data' => new DailyReportResource($dailyReport->fresh(['reviewer'])),
        ]);
    }

    public function destroy(Request $request, DailyReport $dailyReport)
    {
        $this->authorize('delete', $dailyReport);

        $dailyReport->delete();

        $this->auditLog->log('daily_report.deleted', $dailyReport, $request->user());

        return response()->json(['success' => true, 'message' => 'Daily report deleted.']);
    }
}
