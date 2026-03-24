<?php

namespace Tests\Feature\Api\Admin;

use App\Models\LabBooking;
use App\Models\LabMaintenanceBlock;
use App\Models\LabSpace;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LabBlockAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private LabSpace $space;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->space = LabSpace::factory()->create();
    }

    private function authenticatedRequest(
        string $method,
        string $uri,
        array $data = [],
        ?User $user = null
    ): TestResponse {
        $user = $user ?? $this->admin;
        return $this->actingAs($user, 'web')->json($method, $uri, $data);
    }

    #[Test]
    public function admin_can_create_maintenance_block()
    {
        $blockData = [
            'block_type' => 'maintenance',
            'title' => 'Equipment Servicing',
            'reason' => 'Annual PCR calibration',
            'starts_at' => '2026-03-15T08:00:00',
            'ends_at' => '2026-03-15T18:00:00',
        ];

        $response = $this->authenticatedRequest(
            'POST',
            "/api/spaces/{$this->space->id}/blocks",
            $blockData
        );

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.block_type', 'maintenance');
        $response->assertJsonPath('data.title', 'Equipment Servicing');

        $this->assertDatabaseHas('lab_maintenance_blocks', [
            'lab_space_id' => $this->space->id,
            'block_type' => 'maintenance',
            'title' => 'Equipment Servicing',
        ]);
    }

    #[Test]
    public function admin_can_create_holiday_block()
    {
        $blockData = [
            'block_type' => 'holiday',
            'title' => 'Public Holiday',
            'starts_at' => '2026-04-01T08:00:00',
            'ends_at' => '2026-04-01T20:00:00',
        ];

        $response = $this->authenticatedRequest(
            'POST',
            "/api/spaces/{$this->space->id}/blocks",
            $blockData
        );

        $response->assertStatus(201);
        $response->assertJsonPath('data.block_type', 'holiday');
    }

    #[Test]
    public function block_creation_auto_reschedules_conflicting_bookings()
    {
        $date = Carbon::parse('2026-03-15');
        
        // Create a booking that will conflict
        $booking = LabBooking::factory()->create([
            'lab_space_id' => $this->space->id,
            'starts_at' => $date->copy()->setHour(10)->setMinute(0),
            'ends_at' => $date->copy()->setHour(12)->setMinute(0),
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        $blockData = [
            'block_type' => 'maintenance',
            'title' => 'Equipment Servicing',
            'starts_at' => $date->copy()->setHour(8)->toIso8601String(),
            'ends_at' => $date->copy()->setHour(20)->toIso8601String(),
        ];

        $response = $this->authenticatedRequest(
            'POST',
            "/api/spaces/{$this->space->id}/blocks",
            $blockData
        );

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        // Check that rescheduled data is in response
        $this->assertNotEmpty($response->json('rescheduled'));

        // Verify booking was rescheduled
        $rescheduledBooking = $booking->fresh();
        $this->assertNotEquals(
            $booking->starts_at->format('Y-m-d H:00'),
            $rescheduledBooking->starts_at->format('Y-m-d H:00')
        );
    }

    #[Test]
    public function admin_can_list_blocks_for_space()
    {
        // Create multiple blocks
        LabMaintenanceBlock::factory()
            ->count(3)
            ->for($this->space)
            ->create(['created_by' => $this->admin->id]);

        $response = $this->authenticatedRequest(
            'GET',
            "/api/spaces/{$this->space->id}/blocks"
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertCount(3, $response->json('data'));
    }

    #[Test]
    public function admin_can_filter_blocks_by_date_range()
    {
        $march = LabMaintenanceBlock::factory()
            ->create([
                'lab_space_id' => $this->space->id,
                'starts_at' => Carbon::parse('2026-03-15'),
                'ends_at' => Carbon::parse('2026-03-15'),
                'created_by' => $this->admin->id,
            ]);

        $april = LabMaintenanceBlock::factory()
            ->create([
                'lab_space_id' => $this->space->id,
                'starts_at' => Carbon::parse('2026-04-15'),
                'ends_at' => Carbon::parse('2026-04-15'),
                'created_by' => $this->admin->id,
            ]);

        $response = $this->authenticatedRequest(
            'GET',
            "/api/spaces/{$this->space->id}/blocks",
            [
                'start' => '2026-03-01T00:00:00Z',
                'end' => '2026-03-31T23:59:59Z',
            ]
        );

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    #[Test]
    public function admin_can_filter_blocks_by_type()
    {
        LabMaintenanceBlock::factory()
            ->count(2)
            ->for($this->space)
            ->create([
                'block_type' => 'maintenance',
                'created_by' => $this->admin->id,
            ]);

        LabMaintenanceBlock::factory()
            ->for($this->space)
            ->create([
                'block_type' => 'holiday',
                'created_by' => $this->admin->id,
            ]);

        $response = $this->authenticatedRequest(
            'GET',
            "/api/spaces/{$this->space->id}/blocks",
            ['block_type' => 'maintenance']
        );

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    #[Test]
    public function admin_can_update_block()
    {
        $block = LabMaintenanceBlock::factory()
            ->for($this->space)
            ->create(['created_by' => $this->admin->id]);

        $updateData = [
            'title' => 'Updated Maintenance',
            'reason' => 'Extended servicing',
        ];

        $response = $this->authenticatedRequest(
            'PATCH',
            "/api/spaces/{$this->space->id}/blocks/{$block->id}",
            $updateData
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated Maintenance');

        $this->assertDatabaseHas('lab_maintenance_blocks', [
            'id' => $block->id,
            'title' => 'Updated Maintenance',
        ]);
    }

    #[Test]
    public function updating_block_dates_reschedules_bookings()
    {
        $block = LabMaintenanceBlock::factory()
            ->for($this->space)
            ->create([
                'starts_at' => Carbon::parse('2026-03-15T10:00:00'),
                'ends_at' => Carbon::parse('2026-03-15T12:00:00'),
                'created_by' => $this->admin->id,
            ]);

        // Create a booking that conflicts with new dates
        $date = Carbon::parse('2026-03-16');
        $booking = LabBooking::factory()->create([
            'lab_space_id' => $this->space->id,
            'starts_at' => $date->copy()->setHour(10)->setMinute(0),
            'ends_at' => $date->copy()->setHour(12)->setMinute(0),
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        $originalStartTime = $booking->starts_at;

        $updateData = [
            'starts_at' => '2026-03-16T08:00:00',
            'ends_at' => '2026-03-16T20:00:00',
        ];

        $response = $this->authenticatedRequest(
            'PATCH',
            "/api/spaces/{$this->space->id}/blocks/{$block->id}",
            $updateData
        );

        $response->assertStatus(200);

        // Check that booking was rescheduled
        $booking->refresh();
        $this->assertNotEquals(
            $originalStartTime->format('Y-m-d'),
            $booking->starts_at->format('Y-m-d')
        );
    }

    #[Test]
    public function admin_can_delete_block()
    {
        $block = LabMaintenanceBlock::factory()
            ->for($this->space)
            ->create(['created_by' => $this->admin->id]);

        $response = $this->authenticatedRequest(
            'DELETE',
            "/api/spaces/{$this->space->id}/blocks/{$block->id}"
        );

        $response->assertStatus(204);

        $this->assertDatabaseMissing('lab_maintenance_blocks', [
            'id' => $block->id,
        ]);
    }

    #[Test]
    public function validation_error_on_missing_required_fields()
    {
        $response = $this->authenticatedRequest(
            'POST',
            "/api/spaces/{$this->space->id}/blocks",
            [
                'block_type' => 'maintenance',
                // Missing title, starts_at, ends_at
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'starts_at', 'ends_at']);
    }

    #[Test]
    public function validation_error_on_invalid_block_type()
    {
        $response = $this->authenticatedRequest(
            'POST',
            "/api/spaces/{$this->space->id}/blocks",
            [
                'block_type' => 'invalid_type',
                'title' => 'Test',
                'starts_at' => '2026-03-15T08:00:00',
                'ends_at' => '2026-03-15T18:00:00',
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['block_type']);
    }

    #[Test]
    public function validation_error_when_end_before_start()
    {
        $response = $this->authenticatedRequest(
            'POST',
            "/api/spaces/{$this->space->id}/blocks",
            [
                'block_type' => 'maintenance',
                'title' => 'Test',
                'starts_at' => '2026-03-15T18:00:00',
                'ends_at' => '2026-03-15T08:00:00', // Before start
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ends_at']);
    }

    #[Test]
    public function block_for_non_existent_space_returns_404()
    {
        $response = $this->authenticatedRequest(
            'GET',
            '/api/spaces/99999/blocks'
        );

        $response->assertStatus(404);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_blocks()
    {
        $response = $this->json(
            'GET',
            "/api/spaces/{$this->space->id}/blocks"
        );

        $response->assertStatus(401);
    }

    #[Test]
    public function non_admin_user_cannot_create_blocks()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user, 'web')->json(
            'POST',
            "/api/spaces/{$this->space->id}/blocks",
            [
                'block_type' => 'maintenance',
                'title' => 'Test',
                'starts_at' => '2026-03-15T08:00:00',
                'ends_at' => '2026-03-15T18:00:00',
            ]
        );

        // Should be 403 Forbidden based on middleware
        $response->assertStatus(403);
    }

    #[Test]
    public function rescheduled_bookings_include_user_info()
    {
        $date = Carbon::parse('2026-03-15');
        $user = User::factory()->create(['username' => 'testuser']);
        
        $booking = LabBooking::factory()->create([
            'lab_space_id' => $this->space->id,
            'user_id' => $user->id,
            'starts_at' => $date->copy()->setHour(10)->setMinute(0),
            'ends_at' => $date->copy()->setHour(12)->setMinute(0),
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        $blockData = [
            'block_type' => 'maintenance',
            'title' => 'Equipment Servicing',
            'starts_at' => $date->copy()->setHour(8)->toIso8601String(),
            'ends_at' => $date->copy()->setHour(20)->toIso8601String(),
        ];

        $response = $this->authenticatedRequest(
            'POST',
            "/api/spaces/{$this->space->id}/blocks",
            $blockData
        );

        $response->assertStatus(201);
        $rescheduled = $response->json('rescheduled');
        
        $this->assertNotEmpty($rescheduled);
        $this->assertEquals('testuser', $rescheduled[0]['user']);
        $this->assertEquals($booking->id, $rescheduled[0]['booking_id']);
    }

    #[Test]
    public function lab_supervisor_can_create_block_for_assigned_lab()
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('lab_supervisor');

        // Assign supervisor to the lab space
        $supervisor->assignedLabSpaces()->attach($this->space->id);

        $blockData = [
            'block_type' => 'maintenance',
            'title' => 'Equipment Servicing',
            'reason' => 'Annual PCR calibration',
            'starts_at' => '2026-03-15T08:00:00',
            'ends_at' => '2026-03-15T18:00:00',
        ];

        $response = $this->authenticatedRequest('POST', "/api/spaces/{$this->space->id}/blocks", $blockData, $supervisor);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.title', 'Equipment Servicing');
    }

    #[Test]
    public function lab_supervisor_cannot_create_block_for_non_assigned_lab()
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('lab_supervisor');

        // Supervisor is NOT assigned to this space
        
        $blockData = [
            'block_type' => 'maintenance',
            'title' => 'Equipment Servicing',
            'reason' => 'Annual PCR calibration',
            'starts_at' => '2026-03-15T08:00:00',
            'ends_at' => '2026-03-15T18:00:00',
        ];

        $response = $this->authenticatedRequest('POST', "/api/spaces/{$this->space->id}/blocks", $blockData, $supervisor);

        $response->assertStatus(403);
        $response->assertJsonPath('success', false);
    }

    #[Test]
    public function lab_supervisor_cannot_update_block_for_non_assigned_lab()
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('lab_supervisor');
        // Supervisor NOT assigned to this space

        $block = LabMaintenanceBlock::factory()
            ->for($this->space)
            ->create(['created_by' => $this->admin->id]);

        $updateData = [
            'title' => 'Updated Title',
        ];

        $response = $this->authenticatedRequest('PATCH', "/api/spaces/{$this->space->id}/blocks/{$block->id}", $updateData, $supervisor);

        $response->assertStatus(403);
    }

    #[Test]
    public function lab_supervisor_can_update_block_for_assigned_lab()
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('lab_supervisor');
        $supervisor->assignedLabSpaces()->attach($this->space->id);

        $block = LabMaintenanceBlock::factory()
            ->for($this->space)
            ->create(['created_by' => $this->admin->id]);

        $updateData = [
            'title' => 'Updated Title',
        ];

        $response = $this->authenticatedRequest('PATCH', "/api/spaces/{$this->space->id}/blocks/{$block->id}", $updateData, $supervisor);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated Title');
    }

    #[Test]
    public function lab_supervisor_cannot_delete_block_for_non_assigned_lab()
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('lab_supervisor');

        $block = LabMaintenanceBlock::factory()
            ->for($this->space)
            ->create(['created_by' => $this->admin->id]);

        $response = $this->authenticatedRequest('DELETE', "/api/spaces/{$this->space->id}/blocks/{$block->id}", [], $supervisor);

        $response->assertStatus(403);
    }

    #[Test]
    public function lab_supervisor_can_delete_block_for_assigned_lab()
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('lab_supervisor');
        $supervisor->assignedLabSpaces()->attach($this->space->id);

        $block = LabMaintenanceBlock::factory()
            ->for($this->space)
            ->create(['created_by' => $this->admin->id]);

        $response = $this->authenticatedRequest('DELETE', "/api/spaces/{$this->space->id}/blocks/{$block->id}", [], $supervisor);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('lab_maintenance_blocks', ['id' => $block->id]);
    }

    #[Test]
    public function lab_supervisor_index_returns_only_assigned_labs()
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('lab_supervisor');
        
        // Create multiple spaces
        $assignedSpace = LabSpace::factory()->create();
        $unassignedSpace = LabSpace::factory()->create();
        
        // Assign supervisor only to one
        $supervisor->assignedLabSpaces()->attach($assignedSpace->id);

        // Create blocks for both spaces
        LabMaintenanceBlock::factory()
            ->for($assignedSpace)
            ->create(['created_by' => $this->admin->id]);

        LabMaintenanceBlock::factory()
            ->for($unassignedSpace)
            ->create(['created_by' => $this->admin->id]);

        $response = $this->authenticatedRequest('GET', "/api/spaces/{$assignedSpace->id}/blocks", [], $supervisor);

        $response->assertStatus(200);
        
        // Verify supervisor can access assigned space
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    #[Test]
    public function lab_supervisor_cannot_access_index_for_non_assigned_lab()
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('lab_supervisor');
        // Not assigned to any space

        $response = $this->authenticatedRequest('GET', "/api/spaces/{$this->space->id}/blocks", [], $supervisor);

        $response->assertStatus(403);
        $response->assertJsonPath('success', false);
    }

    #[Test]
    public function lab_manager_can_access_all_labs_without_restriction()
    {
        $manager = User::factory()->create();
        $manager->assignRole('lab_manager');

        $space1 = LabSpace::factory()->create();
        $space2 = LabSpace::factory()->create();

        $blockData = [
            'block_type' => 'maintenance',
            'title' => 'Equipment Servicing',
            'starts_at' => '2026-03-15T08:00:00',
            'ends_at' => '2026-03-15T18:00:00',
        ];

        // Lab manager should be able to create blocks in any space
        $response1 = $this->authenticatedRequest('POST', "/api/spaces/{$space1->id}/blocks", $blockData, $manager);

        $response2 = $this->authenticatedRequest('POST', "/api/spaces/{$space2->id}/blocks", $blockData, $manager);

        $response1->assertStatus(201);
        $response2->assertStatus(201);
    }
}

