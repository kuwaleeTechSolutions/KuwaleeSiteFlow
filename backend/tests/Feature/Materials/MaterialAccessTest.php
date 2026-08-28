<?php

namespace Tests\Feature\Materials;

use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class MaterialAccessTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_store_manager_can_create_a_material(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $storeManager = $this->createUserWithRole($organization, 'store_manager');

        $response = $this->actingAs($storeManager)->postJson('/api/materials', [
            'material_code' => 'MAT-001',
            'material_name' => 'OPC 53 Grade Cement',
            'unit' => 'bags',
            'minimum_stock' => 200,
        ]);

        $response->assertCreated();
    }

    public function test_material_code_must_be_unique_within_organization(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $storeManager = $this->createUserWithRole($organization, 'store_manager');
        $this->createMaterial($organization, ['material_code' => 'MAT-DUP']);

        $response = $this->actingAs($storeManager)->postJson('/api/materials', [
            'material_code' => 'MAT-DUP',
            'material_name' => 'Duplicate',
            'unit' => 'kg',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('material_code');
    }

    public function test_cannot_view_a_material_from_another_organization(): void
    {
        $orgA = $this->createOrganizationWithRoles();
        $orgB = $this->createOrganizationWithRoles();
        $ownerA = $this->createUserWithRole($orgA, 'owner');
        $materialInB = $this->createMaterial($orgB);

        $this->actingAs($ownerA)->getJson("/api/materials/{$materialInB->uuid}")->assertStatus(403);
    }

    public function test_hr_labour_manager_cannot_access_materials_by_default(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $hr = $this->createUserWithRole($organization, 'hr_labour_manager');
        $material = $this->createMaterial($organization);

        $this->actingAs($hr)->getJson("/api/materials/{$material->uuid}")->assertStatus(403);
    }
}
