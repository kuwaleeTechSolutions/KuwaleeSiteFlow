<?php

namespace Tests\Concerns;

use App\Models\BoqItem;
use App\Models\BoqRevision;
use App\Models\ComplianceItem;
use App\Models\DailyReport;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Material;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Worker;
use App\Services\RoleService;

trait SetsUpOrganizations
{
    /**
     * Create an organization with its full set of default (cloned) roles,
     * exactly as would happen via Api\System\OrganizationController::store().
     */
    protected function createOrganizationWithRoles(): Organization
    {
        $organization = Organization::factory()->create();
        app(RoleService::class)->seedDefaultRolesFor($organization);

        return $organization;
    }

    /**
     * Create a user belonging to the given organization and assign it the
     * named default role slug (e.g. 'owner', 'site_supervisor').
     */
    protected function createUserWithRole(Organization $organization, string $roleSlug): User
    {
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $role = Role::where('organization_id', $organization->id)->where('slug', $roleSlug)->firstOrFail();
        app(RoleService::class)->assignRole($user, $role);

        return $user->fresh(['roles.permissions']);
    }

    protected function createProject(Organization $organization, array $attributes = []): Project
    {
        return Project::factory()->create(array_merge(
            ['organization_id' => $organization->id],
            $attributes
        ));
    }

    protected function createSite(Project $project, array $attributes = []): Site
    {
        return Site::factory()->forProject($project)->create($attributes);
    }

    protected function createDailyReport(Site $site, User $creator, array $attributes = []): DailyReport
    {
        return DailyReport::factory()->forSite($site, $creator->id)->create($attributes);
    }

    protected function createWorker(Organization $organization, array $attributes = []): Worker
    {
        return Worker::factory()->create(array_merge(
            ['organization_id' => $organization->id],
            $attributes
        ));
    }

    protected function createMaterial(Organization $organization, array $attributes = []): Material
    {
        return Material::factory()->create(array_merge(
            ['organization_id' => $organization->id],
            $attributes
        ));
    }

    protected function createEquipment(Organization $organization, array $attributes = []): Equipment
    {
        return Equipment::factory()->create(array_merge(
            ['organization_id' => $organization->id],
            $attributes
        ));
    }

    /**
     * Creates a BOQ revision #1 with a single item for the given project,
     * returning the created BoqItem — the minimal fixture most
     * Measurement/Billing tests build on top of.
     */
    protected function createBoqItem(Project $project, array $attributes = []): BoqItem
    {
        $revision = BoqRevision::factory()->create([
            'organization_id' => $project->organization_id,
            'project_id' => $project->id,
            'revision_number' => 1,
            'created_by' => User::factory()->create(['organization_id' => $project->organization_id])->id,
        ]);

        return BoqItem::factory()->create(array_merge([
            'organization_id' => $project->organization_id,
            'project_id' => $project->id,
            'boq_revision_id' => $revision->id,
        ], $attributes));
    }

    protected function createDocument(Organization $organization, User $uploader, array $attributes = []): Document
    {
        return Document::factory()->create(array_merge([
            'organization_id' => $organization->id,
            'uploaded_by' => $uploader->id,
        ], $attributes));
    }

    protected function createComplianceItem(Organization $organization, array $attributes = []): ComplianceItem
    {
        return ComplianceItem::factory()->create(array_merge(
            ['organization_id' => $organization->id],
            $attributes
        ));
    }
}
