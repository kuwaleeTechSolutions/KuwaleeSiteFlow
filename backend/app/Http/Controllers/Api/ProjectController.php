<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\AssignProjectUsersRequest;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\UserResource;
use App\Models\Project;
use App\Services\AuditLogService;
use App\Services\ProjectAssignmentService;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectAssignmentService $assignmentService,
        private readonly AuditLogService $auditLog,
    ) {
    }

    /**
     * Lists projects. The OrganizationScope global scope (via
     * BelongsToOrganization on Project) already restricts this to the
     * caller's organization; we ADDITIONALLY restrict to assigned projects
     * unless the user holds org-wide visibility, so a Project Manager
     * never even sees projects they are not assigned to in a list view.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Project::class);

        $user = $request->user();

        $projects = Project::query()
            ->withCount('sites')
            ->with('projectManager')
            ->when(! $user->hasOrgWideVisibility(), function ($query) use ($user) {
                $query->whereHas('assignedUsers', fn ($q) => $q->where('users.id', $user->id));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('project_name', 'like', '%'.$request->input('search').'%')
                    ->orWhere('project_code', 'like', '%'.$request->input('search').'%');
            }))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return ProjectResource::collection($projects)->additional(['success' => true]);
    }

    public function show(Project $project)
    {
        $this->authorize('view', $project);

        return response()->json([
            'success' => true,
            'data' => new ProjectResource($project->loadCount('sites')->load('projectManager')),
        ]);
    }

    public function store(StoreProjectRequest $request)
    {
        $project = Project::create($request->validated());

        $this->auditLog->log('project.created', $project, $request->user(), null, $project->only([
            'project_code', 'project_name', 'contract_value',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Project created successfully.',
            'data' => new ProjectResource($project),
        ], 201);
    }

    public function update(UpdateProjectRequest $request, Project $project)
    {
        $oldValues = $project->only([
            'project_code', 'project_name', 'contract_value', 'status', 'project_manager_id',
        ]);

        $project->update($request->validated());

        $this->auditLog->log('project.updated', $project, $request->user(), $oldValues, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Project updated successfully.',
            'data' => new ProjectResource($project->fresh('projectManager')),
        ]);
    }

    public function destroy(Request $request, Project $project)
    {
        $this->authorize('delete', $project);

        $project->delete(); // soft delete

        $this->auditLog->log('project.deleted', $project, $request->user());

        return response()->json(['success' => true, 'message' => 'Project deleted successfully.']);
    }

    public function assignedUsers(Project $project)
    {
        $this->authorize('view', $project);

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($project->assignedUsers()->get()),
        ]);
    }

    public function assignUsers(AssignProjectUsersRequest $request, Project $project)
    {
        $oldUserIds = $project->assignedUsers()->pluck('users.id')->all();

        $this->assignmentService->assignUsersToProject($project, $request->validated('user_ids'));

        $this->auditLog->log(
            'project.users_assigned',
            $project,
            $request->user(),
            ['user_ids' => $oldUserIds],
            ['user_ids' => $request->validated('user_ids')],
        );

        return response()->json([
            'success' => true,
            'message' => 'Users assigned to project successfully.',
            'data' => UserResource::collection($project->assignedUsers()->get()),
        ]);
    }
}
