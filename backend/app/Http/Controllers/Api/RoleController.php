<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\AuditLogService;
use App\Services\RoleService;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
        private readonly AuditLogService $auditLog,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->where('organization_id', $request->user()->organization_id)
            ->with('permissions')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => RoleResource::collection($roles),
        ]);
    }

    public function show(Request $request, Role $role)
    {
        $this->authorize('view', $role);

        return response()->json([
            'success' => true,
            'data' => new RoleResource($role->load('permissions')),
        ]);
    }

    public function store(StoreRoleRequest $request)
    {
        $role = Role::create([
            'organization_id' => $request->user()->organization_id,
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug'),
            'description' => $request->validated('description'),
            'is_system' => false,
            'org_wide_visibility' => $request->boolean('org_wide_visibility'),
        ]);

        $this->roleService->updatePermissions($role, $request->validated('permissions'));

        $this->auditLog->log('role.created', $role, $request->user(), null, [
            'name' => $role->name, 'permissions' => $request->validated('permissions'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully.',
            'data' => new RoleResource($role->load('permissions')),
        ], 201);
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $oldValues = $role->only(['name', 'description', 'org_wide_visibility']);

        $role->update($request->safe()->except('permissions'));

        if ($request->has('permissions')) {
            $this->roleService->updatePermissions($role, $request->validated('permissions'));
        }

        $this->auditLog->log('role.updated', $role, $request->user(), $oldValues, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully.',
            'data' => new RoleResource($role->fresh('permissions')),
        ]);
    }

    public function destroy(Request $request, Role $role)
    {
        $this->authorize('delete', $role);

        $role->delete();

        $this->auditLog->log('role.deleted', $role, $request->user());

        return response()->json(['success' => true, 'message' => 'Role deleted successfully.']);
    }
}
