<?php

namespace Tests;

use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Only seed if explicitly needed by the test
        if (property_exists($this, 'shouldSeedRoles') && $this->shouldSeedRoles) {
            $this->seed(RolesPermissionsSeeder::class);
        }
    }

    protected function tearDown(): void
    {
        // Reset foreign key constraints
        if (DB::connection() instanceof \Illuminate\Database\SQLiteConnection) {
            DB::connection()->getPdo()->exec('PRAGMA foreign_keys=ON');
        }
        parent::tearDown();
    }
}
