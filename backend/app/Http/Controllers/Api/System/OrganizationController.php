<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Super Admin only. Reachable exclusively via /api/system/* routes, which
 * are gated by the `auth:sanctum` + a dedicated `super_admin` middleware
 * (see routes/api.php) — never mounted under the tenant-facing
 * `org.context` middleware group, so a compromised tenant session can never
 * reach this controller regardless of any permission it might hold.
 */
class OrganizationController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
        private readonly AuditLogService $auditLog,
    ) {
    }

    public function index(Request $request)
    {
        $organizations = Organization::query()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->input('search').'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return OrganizationResource::collection($organizations)->additional(['success' => true]);
    }

    public function show(Organization $organization)
    {
        return response()->json(['success' => true, 'data' => new OrganizationResource($organization)]);
    }

    /**
     * Provision a brand-new organization together with its first Owner
     * user and the cloned set of default system roles, all inside a single
     * transaction so a failure never leaves a half-provisioned tenant.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gst_number' => ['nullable', 'string', 'max:30'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255'],
            'owner_password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $organization = DB::transaction(function () use ($validated) {
            $organization = Organization::create([
                'name' => $validated['name'],
                'legal_name' => $validated['legal_name'] ?? null,
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'gst_number' => $validated['gst_number'] ?? null,
                'status' => 'trial',
                'settings' => [
                    'currency' => 'INR',
                    'timezone' => 'Asia/Kolkata',
                    'date_format' => 'DD-MM-YYYY',
                ],
            ]);

            $this->roleService->seedDefaultRolesFor($organization);

            $ownerRole = Role::where('organization_id', $organization->id)->where('slug', 'owner')->firstOrFail();

            $owner = User::create([
                'organization_id' => $organization->id,
                'name' => $validated['owner_name'],
                'email' => $validated['owner_email'],
                'password' => Hash::make($validated['owner_password']),
                'status' => 'active',
            ]);

            $this->roleService->assignRole($owner, $ownerRole);

            return $organization;
        });

        $this->auditLog->log('organization.created', $organization, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Organization created successfully.',
            'data' => new OrganizationResource($organization),
        ], 201);
    }

    public function update(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'suspended', 'trial'])],
        ]);

        $oldValues = $organization->only(['name', 'status']);
        $organization->update($validated);

        $this->auditLog->log('organization.updated', $organization, $request->user(), $oldValues, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Organization updated successfully.',
            'data' => new OrganizationResource($organization),
        ]);
    }
}
