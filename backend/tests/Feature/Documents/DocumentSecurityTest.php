<?php

namespace Tests\Feature\Documents;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

/**
 * Covers brief §31 "Document Security" test requirements directly:
 *  - User from Organization A cannot access Organization B document.
 *  - User cannot download restricted document without permission.
 *  - Guessing another document ID does not bypass authorization.
 * Plus the full confidentiality-tier matrix from brief §23.
 */
class DocumentSecurityTest extends TestCase
{
    use SetsUpOrganizations;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private-documents');
    }

    public function test_organization_level_document_is_visible_to_any_org_member_with_permission(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $siteSupervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $owner = $this->createUserWithRole($organization, 'owner');
        $document = $this->createDocument($organization, $owner, ['confidentiality_level' => 'organization']);

        $this->actingAs($siteSupervisor)->getJson("/api/documents/{$document->uuid}")->assertOk();
    }

    public function test_project_level_document_requires_assignment(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $unassignedSupervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $assignedSupervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($assignedSupervisor->id);

        $document = $this->createDocument($organization, $owner, [
            'confidentiality_level' => 'project',
            'project_id' => $project->id,
        ]);

        $this->actingAs($assignedSupervisor)->getJson("/api/documents/{$document->uuid}")->assertOk();
        $this->actingAs($unassignedSupervisor)->getJson("/api/documents/{$document->uuid}")->assertStatus(403);
    }

    public function test_restricted_document_requires_org_wide_role_or_explicit_share(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $projectManager = $this->createUserWithRole($organization, 'project_manager'); // NOT org-wide
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($projectManager->id);

        $document = $this->createDocument($organization, $owner, [
            'confidentiality_level' => 'restricted',
            'project_id' => $project->id,
        ]);

        // Project assignment ALONE is not sufficient for 'restricted'.
        $this->actingAs($projectManager)->getJson("/api/documents/{$document->uuid}")->assertStatus(403);

        // Owner (org-wide) can access it.
        $this->actingAs($owner)->getJson("/api/documents/{$document->uuid}")->assertOk();

        // After an explicit share, the project manager CAN access it.
        $document->sharedWith()->attach($projectManager->id, ['shared_by' => $owner->id]);
        $this->actingAs($projectManager)->getJson("/api/documents/{$document->uuid}")->assertOk();
    }

    public function test_management_only_document_is_never_unlocked_by_sharing(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $projectManager = $this->createUserWithRole($organization, 'project_manager');

        $document = $this->createDocument($organization, $owner, ['confidentiality_level' => 'management_only']);

        // Even with an explicit share, management_only stays locked to
        // non-org-wide users — this is the strictest tier.
        $document->sharedWith()->attach($projectManager->id, ['shared_by' => $owner->id]);

        $this->actingAs($projectManager)->getJson("/api/documents/{$document->uuid}")->assertStatus(403);
        $this->actingAs($owner)->getJson("/api/documents/{$document->uuid}")->assertOk();
    }

    public function test_user_from_organization_a_cannot_access_organization_b_document(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $ownerA = $this->createUserWithRole($orgA, 'owner');
        $ownerB = $this->createUserWithRole($orgB, 'owner');
        $documentInB = $this->createDocument($orgB, $ownerB, ['confidentiality_level' => 'organization']);

        // Even org-wide visibility in Org A does not cross into Org B.
        $this->actingAs($ownerA)->getJson("/api/documents/{$documentInB->uuid}")->assertStatus(403);
    }

    public function test_guessing_another_documents_uuid_does_not_bypass_authorization(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');

        $managementDoc = $this->createDocument($organization, $owner, ['confidentiality_level' => 'management_only']);

        // A same-organization, permission-holding user still cannot access
        // a document whose confidentiality tier excludes them, even when
        // they know (or guess) the exact UUID.
        $response = $this->actingAs($supervisor)->getJson("/api/documents/{$managementDoc->uuid}");
        $response->assertStatus(403);
    }

    public function test_downloading_a_restricted_document_without_permission_is_denied(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        // client_readonly has documents.view but NOT documents.download.
        $client = $this->createUserWithRole($organization, 'client_readonly');
        $document = $this->createDocument($organization, $owner, ['confidentiality_level' => 'organization']);
        Storage::disk('private-documents')->put($document->disk_path, 'fake content');

        $this->actingAs($client)->getJson("/api/documents/{$document->uuid}")->assertOk(); // view is fine
        $this->actingAs($client)->getJson("/api/documents/{$document->uuid}/download")->assertStatus(403); // download is not
    }

    public function test_successful_download_is_audit_logged(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $document = $this->createDocument($organization, $owner, ['confidentiality_level' => 'organization']);
        Storage::disk('private-documents')->put($document->disk_path, 'fake content');

        $this->actingAs($owner)->getJson("/api/documents/{$document->uuid}/download")->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.downloaded',
            'entity_id' => $document->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_document_upload_rejects_disguised_executable(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');

        $maliciousFile = UploadedFile::fake()->createWithContent('report.pdf', "<?php system(\$_GET['cmd']); ?>");

        $response = $this->actingAs($owner)->postJson('/api/documents', [
            'file' => $maliciousFile,
            'category' => 'other',
            'title' => 'Fake Report',
            'confidentiality_level' => 'organization',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_document_response_never_exposes_disk_path(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $document = $this->createDocument($organization, $owner);

        $response = $this->actingAs($owner)->getJson("/api/documents/{$document->uuid}");

        $response->assertOk()->assertJsonMissingPath('data.disk_path');
        $this->assertArrayHasKey('download_url', $response->json('data'));
    }

    public function test_documents_list_only_returns_documents_the_user_can_see(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $projectManager = $this->createUserWithRole($organization, 'project_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($projectManager->id);

        $visible = $this->createDocument($organization, $owner, [
            'confidentiality_level' => 'project', 'project_id' => $project->id, 'title' => 'Visible Doc',
        ]);
        $hidden = $this->createDocument($organization, $owner, [
            'confidentiality_level' => 'management_only', 'title' => 'Hidden Doc',
        ]);

        $response = $this->actingAs($projectManager)->getJson('/api/documents');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');
        $this->assertContains('Visible Doc', $titles);
        $this->assertNotContains('Hidden Doc', $titles);
    }
}
