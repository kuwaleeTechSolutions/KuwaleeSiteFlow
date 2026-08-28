<?php

namespace Tests\Feature\Documents;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class DocumentSharingTest extends TestCase
{
    use SetsUpOrganizations;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private-documents');
    }

    public function test_owner_can_share_a_restricted_document_with_a_specific_user(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $client = $this->createUserWithRole($organization, 'client_readonly');
        $document = $this->createDocument($organization, $owner, ['confidentiality_level' => 'restricted']);

        $response = $this->actingAs($owner)->postJson("/api/documents/{$document->uuid}/share", [
            'user_ids' => [$client->id],
        ]);

        $response->assertOk();
        $this->assertTrue($document->fresh()->isSharedWith($client->id));
    }

    public function test_sharing_is_audited(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $client = $this->createUserWithRole($organization, 'client_readonly');
        $document = $this->createDocument($organization, $owner, ['confidentiality_level' => 'restricted']);

        $this->actingAs($owner)->postJson("/api/documents/{$document->uuid}/share", [
            'user_ids' => [$client->id],
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'document.shared', 'entity_id' => $document->id]);
    }

    public function test_user_without_documents_share_permission_cannot_share(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        // site_supervisor holds documents.view/upload but not documents.share.
        $supervisor = $this->createUserWithRole($organization, 'site_supervisor');
        $target = User::factory()->create(['organization_id' => $organization->id]);
        $document = $this->createDocument($organization, $owner, ['confidentiality_level' => 'organization']);

        $response = $this->actingAs($supervisor)->postJson("/api/documents/{$document->uuid}/share", [
            'user_ids' => [$target->id],
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_share_a_document_from_another_organization(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $ownerA = $this->createUserWithRole($orgA, 'owner');
        $ownerB = $this->createUserWithRole($orgB, 'owner');
        $documentInB = $this->createDocument($orgB, $ownerB, ['confidentiality_level' => 'organization']);

        $response = $this->actingAs($ownerA)->postJson("/api/documents/{$documentInB->uuid}/share", [
            'user_ids' => [$ownerA->id],
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_share_with_a_user_from_a_different_organization(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($orgA, 'owner');
        $userInB = User::factory()->create(['organization_id' => $orgB->id]);
        $document = $this->createDocument($orgA, $owner, ['confidentiality_level' => 'restricted']);

        $response = $this->actingAs($owner)->postJson("/api/documents/{$document->uuid}/share", [
            'user_ids' => [$userInB->id],
        ]);

        $response->assertStatus(422);
    }

    public function test_uploader_can_delete_their_own_document(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $projectManager = $this->createUserWithRole($organization, 'project_manager');
        $project = $this->createProject($organization);
        $project->assignedUsers()->attach($projectManager->id);
        $document = $this->createDocument($organization, $projectManager, ['confidentiality_level' => 'organization']);

        $this->actingAs($projectManager)->deleteJson("/api/documents/{$document->uuid}")->assertOk();
        $this->assertSoftDeleted('documents', ['id' => $document->id]);
    }

    public function test_non_uploader_without_org_wide_visibility_cannot_delete_document(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $projectManagerA = $this->createUserWithRole($organization, 'project_manager');
        $projectManagerB = $this->createUserWithRole($organization, 'project_manager');
        $document = $this->createDocument($organization, $projectManagerA, ['confidentiality_level' => 'organization']);

        $response = $this->actingAs($projectManagerB)->deleteJson("/api/documents/{$document->uuid}");

        $response->assertStatus(403);
    }
}
