<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\AssignSiteUsersRequest;
use App\Http\Requests\Site\StoreSiteRequest;
use App\Http\Requests\Site\UpdateSiteRequest;
use App\Http\Resources\SiteResource;
use App\Http\Resources\UserResource;
use App\Models\Project;
use App\Models\Site;
use App\Services\AuditLogService;
use App\Services\ProjectAssignmentService;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function __construct(
        private readonly ProjectAssignmentService $assignmentService,
        private readonly AuditLogService $auditLog,
    ) {
    }

    /**
     * Listing ALL sites under a project is intentionally gated by
     * PROJECT-level access only (org-wide visibility or explicit project
     * assignment) — a Site Supervisor who is assigned only to one specific
     * site, but not to the project itself, is not meant to browse every
     * sibling site under that project. They can still directly fetch the
     * one site they ARE assigned to via GET /api/sites/{site}, whose
     * `view` policy additionally accepts site-level assignment.
     */
    public function index(Request $request, Project $project)
    {
        $this->authorize('viewAny', [Site::class, $project]);

        $sites = $project->sites()
            ->with('siteManager')
            ->orderBy('site_name')
            ->paginate($request->integer('per_page', 20));

        return SiteResource::collection($sites)->additional(['success' => true]);
    }

    public function show(Site $site)
    {
        $this->authorize('view', $site);

        return response()->json([
            'success' => true,
            'data' => new SiteResource($site->load('siteManager', 'project')),
        ]);
    }

    public function store(StoreSiteRequest $request, Project $project)
    {
        $site = $project->sites()->create(array_merge(
            $request->validated(),
            ['organization_id' => $project->organization_id],
        ));

        $this->auditLog->log('site.created', $site, $request->user(), null, $site->only(['site_code', 'site_name']));

        return response()->json([
            'success' => true,
            'message' => 'Site created successfully.',
            'data' => new SiteResource($site),
        ], 201);
    }

    public function update(UpdateSiteRequest $request, Site $site)
    {
        $oldValues = $site->only(['site_code', 'site_name', 'status', 'site_manager_id']);

        $site->update($request->validated());

        $this->auditLog->log('site.updated', $site, $request->user(), $oldValues, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Site updated successfully.',
            'data' => new SiteResource($site->fresh('siteManager')),
        ]);
    }

    public function destroy(Request $request, Site $site)
    {
        $this->authorize('delete', $site);

        $site->delete();

        $this->auditLog->log('site.deleted', $site, $request->user());

        return response()->json(['success' => true, 'message' => 'Site deleted successfully.']);
    }

    public function assignUsers(AssignSiteUsersRequest $request, Site $site)
    {
        $oldUserIds = $site->assignedUsers()->pluck('users.id')->all();

        $this->assignmentService->assignUsersToSite($site, $request->validated('user_ids'));

        $this->auditLog->log(
            'site.users_assigned',
            $site,
            $request->user(),
            ['user_ids' => $oldUserIds],
            ['user_ids' => $request->validated('user_ids')],
        );

        return response()->json([
            'success' => true,
            'message' => 'Users assigned to site successfully.',
            'data' => UserResource::collection($site->assignedUsers()->get()),
        ]);
    }
}
