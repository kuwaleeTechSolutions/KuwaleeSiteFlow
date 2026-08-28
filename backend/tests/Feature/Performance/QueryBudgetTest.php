<?php

namespace Tests\Feature\Performance;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\SetsUpOrganizations;
use Tests\TestCase;

class QueryBudgetTest extends TestCase
{
    use SetsUpOrganizations;

    public function test_project_dashboard_stays_within_query_budget(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        $project = $this->createProject($organization);
        $this->createSite($project);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($owner)->getJson("/api/projects/{$project->uuid}/dashboard")->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(25, $count, "Project dashboard query count exceeded budget: {$count}");
    }

    public function test_document_list_stays_within_query_budget(): void
    {
        $organization = $this->createOrganizationWithRoles();
        $owner = $this->createUserWithRole($organization, 'owner');
        for ($i = 0; $i < 10; $i++) {
            $this->createDocument($organization, $owner, ['title' => "Document {$i}"]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($owner)->getJson('/api/documents')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(12, $count, "Document list query count exceeded budget: {$count}");
    }
}
