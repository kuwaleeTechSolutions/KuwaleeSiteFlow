<?php

namespace Tests;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Every test starts with the canonical permission catalogue and
        // system role templates available, mirroring a real deployment.
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleTemplateSeeder::class);
    }
}
