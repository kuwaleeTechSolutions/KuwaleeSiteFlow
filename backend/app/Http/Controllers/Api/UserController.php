<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\AssignRolesRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\SyncUserPermissionsRequest;
use App\Models\Permission;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
        private readonly AuditLogService $auditLog,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        // NOTE: User intentionally does NOT use the BelongsToOrganization
        // global scope (Auth itself resolves User rows before an
        // authenticated context exists, e.g. during login lookup), so this
        // organization filter is applied explicitly here instead.
        $users = User::query()
            ->where('organization_id', $request->user()->organization_id)
            ->with('roles', 'directPermissions')
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->input('search').'%')
                    ->orWhere('email', 'like', '%'.$request->input('search').'%');
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return UserResource::collection($users)->additional(['success' => true]);
    }

    public function show(Request $request, User $user)
    {
        $this->authorize('view', $user);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user->load('roles.permissions', 'directPermissions')),
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'organization_id' => $request->user()->organization_id,
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'phone' => $request->validated('phone'),
                'password' => Hash::make($request->validated('password')),
                'status' => 'active',
            ]);

            $roles = Role::whereIn('id', $request->validated('role_ids'))->get();
            foreach ($roles as $role) {
                $this->roleService->assignRole($user, $role);
            }

            return $user;
        });

        $this->auditLog->log('user.created', $user, $request->user(), null, $user->only(['name', 'email']));

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => new UserResource($user->load('roles')),
        ], 201);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $oldValues = $user->only(['name', 'email', 'phone', 'status']);

        $user->update($request->validated());

        $this->auditLog->log('user.updated', $user, $request->user(), $oldValues, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => new UserResource($user->fresh('roles')),
        ]);
    }

    public function assignRoles(AssignRolesRequest $request, User $user)
    {
        $oldRoleIds = $user->roles()->pluck('roles.id')->all();

        DB::transaction(function () use ($request, $user) {
            $roles = Role::whereIn('id', $request->validated('role_ids'))->get();
            $user->roles()->detach();
            foreach ($roles as $role) {
                $this->roleService->assignRole($user, $role);
            }
        });

        $this->auditLog->log(
            'user.role_changed',
            $user,
            $request->user(),
            ['role_ids' => $oldRoleIds],
            ['role_ids' => $request->validated('role_ids')],
        );

        return response()->json([
            'success' => true,
            'message' => 'Roles updated successfully.',
            'data' => new UserResource($user->fresh('roles.permissions')),
        ]);
    }

    public function syncPermissions(SyncUserPermissionsRequest $request, User $user)
    {
        $oldPermissions = $user->directPermissions()->pluck('name')->all();
        $permissions = Permission::whereIn('name', $request->validated('permission_names'))->pluck('id')->all();
        $user->directPermissions()->sync($permissions);
        $user->unsetRelation('directPermissions');
        $user->clearPermissionCache();

        $this->auditLog->log('user.permissions_changed', $user, $request->user(),
            ['direct_permissions' => $oldPermissions],
            ['direct_permissions' => $request->validated('permission_names')]);

        return response()->json([
            'success' => true,
            'message' => 'User permissions updated successfully.',
            'data' => new UserResource($user->fresh(['roles.permissions', 'directPermissions'])),
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);

        $user->delete(); // soft delete

        $this->auditLog->log('user.deleted', $user, $request->user());

        return response()->json(['success' => true, 'message' => 'User deleted successfully.']);
    }
}
