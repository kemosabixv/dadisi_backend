<?php

namespace Tests\Feature\Api\Admin;

use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LabOperationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
    }

    /**
     * Helper to perform an authenticated request using a manual token.
     */
    private function authenticatedRequest(User $user, string $method, string $uri, array $data = []): TestResponse
    {
        return $this->actingAs($user, 'web')->json($method, $uri, $data);
    }

    private function createStaff(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    // ==================== Listing & Supervisor Scoping ====================

    public function test_lab_manager_can_list_all_bookings(): void
    {
        $manager = $this->createStaff('lab_manager');
        $space1 = LabSpace::factory()->create(['name' => 'Space 1']);
        $space2 = LabSpace::factory()->create(['name' => 'Space 2']);

        LabBooking::factory()->create(['lab_space_id' => $space1->id]);
        LabBooking::factory()->create(['lab_space_id' => $space2->id]);

        $response = $this->authenticatedRequest($manager, 'GET', '/api/admin/lab-bookings');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_lab_supervisor_can_only_list_assigned_bookings(): void
    {
        $supervisor = $this->createStaff('lab_supervisor');
        $space1 = LabSpace::factory()->create(['name' => 'Assigned Space']);
        $space2 = LabSpace::factory()->create(['name' => 'Unassigned Space']);

        // Assign supervisor to space 1
        $supervisor->assignedLabSpaces()->attach($space1->id);

        LabBooking::factory()->create(['lab_space_id' => $space1->id, 'title' => 'Assigned Booking']);
        LabBooking::factory()->create(['lab_space_id' => $space2->id, 'title' => 'Unassigned Booking']);

        $response = $this->authenticatedRequest($supervisor, 'GET', '/api/admin/lab-bookings');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lab_space_id', $space1->id);
    }

    // ==================== User Self Check-in ====================

    public function test_registered_user_can_self_check_in_at_valid_time(): void
    {
        $user = User::factory()->create();
        $lab = LabSpace::factory()->create();

        $booking = LabBooking::factory()->create([
            'user_id' => $user->id,
            'lab_space_id' => $lab->id,
            'status' => LabBooking::STATUS_CONFIRMED,
            'starts_at' => now()->subMinutes(5), // Session started 5 min ago
            'ends_at' => now()->addHour(),
        ]);

        $response = $this->authenticatedRequest($user, 'PUT', "/api/bookings/{$booking->id}/check-in");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertNotNull($booking->fresh()->checked_in_at);
    }

    public function test_user_cannot_check_in_too_early(): void
    {
        $user = User::factory()->create();
        $lab = LabSpace::factory()->create();

        $booking = LabBooking::factory()->create([
            'user_id' => $user->id,
            'lab_space_id' => $lab->id,
            'status' => LabBooking::STATUS_CONFIRMED,
            'starts_at' => now()->addMinutes(30), // Start is 30 min away
            'ends_at' => now()->addHours(2),
        ]);

        $response = $this->authenticatedRequest($user, 'PUT', "/api/bookings/{$booking->id}/check-in");

        $response->assertStatus(403);
    }

    // ==================== Staff Guest Check-in ====================

    public function test_staff_can_check_in_guest_booking(): void
    {
        $manager = $this->createStaff('lab_manager');
        $lab = LabSpace::factory()->create();

        $booking = LabBooking::factory()->create([
            'user_id' => null, // Guest
            'guest_name' => 'John Guest',
            'lab_space_id' => $lab->id,
            'status' => LabBooking::STATUS_CONFIRMED,
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->addHour(),
        ]);

        $response = $this->authenticatedRequest($manager, 'PUT', "/api/admin/lab-bookings/{$booking->id}/guest-check-in");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Guest checked in successfully']);

        $this->assertNotNull($booking->fresh()->checked_in_at);
    }

    public function test_supervisor_cannot_check_in_guest_in_unassigned_space(): void
    {
        $supervisor = $this->createStaff('lab_supervisor');
        $lab1 = LabSpace::factory()->create(['name' => 'Assigned']);
        $lab2 = LabSpace::factory()->create(['name' => 'Unassigned']);

        $supervisor->assignedLabSpaces()->attach($lab1->id);

        $booking = LabBooking::factory()->create([
            'user_id' => null,
            'lab_space_id' => $lab2->id,
            'status' => LabBooking::STATUS_CONFIRMED,
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->addHour(),
        ]);

        $response = $this->authenticatedRequest($supervisor, 'PUT', "/api/admin/lab-bookings/{$booking->id}/guest-check-in");

        $response->assertStatus(403);
    }

    // ==================== Deprecated Routes ====================

    public function test_approve_reject_routes_return_404(): void
    {
        $manager = $this->createStaff('lab_manager');
        $booking = LabBooking::factory()->create();

        // These routes were removed from api.php
        $responseApprove = $this->authenticatedRequest($manager, 'PUT', "/api/admin/lab-bookings/{$booking->id}/approve");
        $responseReject = $this->authenticatedRequest($manager, 'PUT', "/api/admin/lab-bookings/{$booking->id}/reject");
        $responseCheckOut = $this->authenticatedRequest($manager, 'PUT', "/api/admin/lab-bookings/{$booking->id}/check-out");

        // These routes were removed from api.php. Laravel might return 405 if it partially matches other routes.
        $responseApprove->assertStatus(405);
        $responseReject->assertStatus(405);
        $responseCheckOut->assertStatus(405);
    }

    // ==================== Automated Commands ====================

    public function test_command_completes_expired_bookings(): void
    {
        $lab = LabSpace::factory()->create();

        // Expired but checked in
        $booking = LabBooking::factory()->create([
            'lab_space_id' => $lab->id,
            'status' => LabBooking::STATUS_CONFIRMED,
            'checked_in_at' => now()->subHours(2),
            'ends_at' => now()->subMinutes(5),
        ]);

        Artisan::call('lab:complete-expired');

        $this->assertEquals(LabBooking::STATUS_COMPLETED, $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->checked_out_at);
    }

    public function test_command_marks_no_shows(): void
    {
        $lab = LabSpace::factory()->create();

        // Expired but NOT checked in
        $booking = LabBooking::factory()->create([
            'lab_space_id' => $lab->id,
            'status' => LabBooking::STATUS_CONFIRMED,
            'checked_in_at' => null,
            'ends_at' => now()->subMinutes(20),
        ]);

        Artisan::call('lab:mark-no-shows');

        $this->assertEquals(LabBooking::STATUS_NO_SHOW, $booking->fresh()->status);
    }

    // ==================== Supervisor Lab Space Management ====================

    public function test_supervisor_can_list_only_assigned_lab_spaces(): void
    {
        $supervisor = $this->createStaff('lab_supervisor');
        $space1 = LabSpace::factory()->create(['name' => 'Assigned Space']);
        $space2 = LabSpace::factory()->create(['name' => 'Unassigned Space']);

        $supervisor->assignedLabSpaces()->attach($space1->id);

        $response = $this->authenticatedRequest($supervisor, 'GET', '/api/admin/spaces');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $space1->id);
    }

    public function test_supervisor_can_edit_assigned_lab_space(): void
    {
        $supervisor = $this->createStaff('lab_supervisor');
        $space = LabSpace::factory()->create(['name' => 'Old Name']);

        $supervisor->assignedLabSpaces()->attach($space->id);

        $response = $this->authenticatedRequest($supervisor, 'PUT', "/api/admin/spaces/{$space->id}", [
            'name' => 'New Name',
            'type' => $space->type,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $space->fresh()->name);
    }

    public function test_supervisor_cannot_edit_unassigned_lab_space(): void
    {
        $supervisor = $this->createStaff('lab_supervisor');
        $space = LabSpace::factory()->create(['name' => 'Unassigned']);

        $response = $this->authenticatedRequest($supervisor, 'PUT', "/api/admin/spaces/{$space->id}", [
            'name' => 'Attempted Name',
            'type' => $space->type,
        ]);

        $response->assertStatus(403);
    }

    public function test_supervisor_cannot_delete_lab_space(): void
    {
        $supervisor = $this->createStaff('lab_supervisor');
        $space = LabSpace::factory()->create();

        $supervisor->assignedLabSpaces()->attach($space->id);

        $response = $this->authenticatedRequest($supervisor, 'DELETE', "/api/admin/spaces/{$space->id}");

        $response->assertStatus(403);
    }

    public function test_supervisor_cannot_create_lab_space(): void
    {
        $supervisor = $this->createStaff('lab_supervisor');

        $response = $this->authenticatedRequest($supervisor, 'POST', '/api/admin/spaces', [
            'name' => 'New Space',
            'type' => 'wet_lab',
            'capacity' => 10,
        ]);

        $response->assertStatus(403);
    }
}
