<?php

namespace Tests\Feature\Lab;

use App\Models\LabBooking;
use App\Models\LabMaintenanceBlock;
use App\Models\MaintenanceBlockRollover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

use PHPUnit\Framework\Attributes\Test;

class LabRolloverRBACTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\RolesPermissionsSeeder::class);
    }

    #[Test]
    public function admin_can_view_rollover_reports()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/lab-maintenance/rollover-reports');

        $response->assertStatus(200);
    }

    #[Test]
    public function lab_manager_can_view_rollover_reports()
    {
        $manager = User::factory()->create();
        $manager->assignRole('lab_manager');

        $response = $this->actingAs($manager)
            ->getJson('/api/admin/lab-maintenance/rollover-reports');

        $response->assertStatus(200);
    }

    #[Test]
    public function lab_supervisor_can_view_rollover_reports()
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('lab_supervisor');

        $response = $this->actingAs($supervisor)
            ->getJson('/api/admin/lab-maintenance/rollover-reports');

        $response->assertStatus(200);
    }

    #[Test]
    public function regular_member_cannot_view_rollover_reports()
    {
        $member = User::factory()->create();
        $member->assignRole('member');

        $response = $this->actingAs($member)
            ->getJson('/api/admin/lab-maintenance/rollover-reports');

        $response->assertStatus(403);
    }

    #[Test]
    public function admin_can_retry_rollover()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $block = LabMaintenanceBlock::factory()->create();
        $rollover = MaintenanceBlockRollover::create([
            'maintenance_block_id' => $block->id,
            'original_booking_id' => LabBooking::factory()->create(['status' => 'pending_user_resolution'])->id,
            'status' => 'escalated',
            'original_booking_data' => []
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/lab-maintenance/rollover-retry/{$rollover->id}");

        if ($response->status() !== 200) {
            $response->dump();
        }

        $response->assertStatus(200);
    }

    #[Test]
    public function lab_supervisor_cannot_retry_rollover()
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('lab_supervisor');

        $block = LabMaintenanceBlock::factory()->create();
        $rollover = MaintenanceBlockRollover::create([
            'maintenance_block_id' => $block->id,
            'original_booking_id' => LabBooking::factory()->create(['status' => 'pending_user_resolution'])->id,
            'status' => 'escalated',
            'original_booking_data' => []
        ]);

        $response = $this->actingAs($supervisor)
            ->postJson("/api/admin/lab-maintenance/rollover-retry/{$rollover->id}");

        $response->assertStatus(403);
    }
}
