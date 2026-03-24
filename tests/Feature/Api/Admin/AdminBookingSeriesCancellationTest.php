<?php

namespace Tests\Feature\Api\Admin;

use App\Models\BookingSeries;
use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingSeriesCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Correct seeder class for roles and permissions
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
    }

    protected function createStaff(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    protected function createSeries(array $attributes = []): BookingSeries
    {
        return BookingSeries::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'lab_space_id' => LabSpace::factory()->create()->id,
            'type' => 'single',
            'status' => 'confirmed',
            'total_hours' => 2.0,
            'reference' => 'REF-' . uniqid(),
        ], $attributes));
    }

    protected function authenticatedRequest(User $user, string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->json($method, $uri, $data);
    }

    public function test_lab_manager_can_cancel_booking_series(): void
    {
        $series = $this->createSeries(['status' => 'confirmed']);
        $booking = LabBooking::factory()->create([
            'booking_series_id' => $series->id,
            'lab_space_id' => $series->lab_space_id,
            'user_id' => $series->user_id,
            'status' => 'confirmed',
            'starts_at' => now()->addDays(1),
        ]);
        
        $manager = $this->createStaff('lab_manager');

        $response = $this->authenticatedRequest(
            $manager,
            'POST',
            "/api/admin/bookings/series/{$series->id}/cancel",
            ['reason' => 'Lab closure']
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'cancelled');

        $this->assertEquals('cancelled', $series->fresh()->status);
        $this->assertEquals('cancelled', $booking->fresh()->status);
        
        $this->assertDatabaseHas('booking_audit_logs', [
            'series_id' => $series->id,
            'action' => 'cancelled',
            'user_id' => $manager->id,
        ]);

        $log = $series->auditLogs()->where('action', 'cancelled')->first();
        $this->assertTrue($log->payload['is_staff_action'] ?? false);
    }

    public function test_admin_can_cancel_booking_series(): void
    {
        $series = $this->createSeries(['status' => 'confirmed']);
        $booking = LabBooking::factory()->create([
            'booking_series_id' => $series->id,
            'lab_space_id' => $series->lab_space_id,
            'user_id' => $series->user_id,
            'status' => 'confirmed',
            'starts_at' => now()->addDays(1),
        ]);
        
        $admin = $this->createStaff('admin');

        $response = $this->authenticatedRequest(
            $admin,
            'POST',
            "/api/admin/bookings/series/{$series->id}/cancel",
            ['reason' => 'System maintenance']
        );

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $series->fresh()->status);
    }

    public function test_lab_supervisor_can_cancel_assigned_series(): void
    {
        $space = LabSpace::factory()->create();
        $series = $this->createSeries(['status' => 'confirmed', 'lab_space_id' => $space->id]);
        $booking = LabBooking::factory()->create([
            'booking_series_id' => $series->id,
            'lab_space_id' => $space->id,
            'user_id' => $series->user_id,
            'status' => 'confirmed',
            'starts_at' => now()->addDays(1),
        ]);
        
        $supervisor = $this->createStaff('lab_supervisor');
        $supervisor->assignedLabSpaces()->attach($space->id);

        $response = $this->authenticatedRequest(
            $supervisor,
            'POST',
            "/api/admin/bookings/series/{$series->id}/cancel",
            ['reason' => 'Staff request']
        );

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $series->fresh()->status);
    }

    public function test_lab_supervisor_cannot_cancel_unassigned_series(): void
    {
        $space1 = LabSpace::factory()->create();
        $space2 = LabSpace::factory()->create();
        $series = $this->createSeries(['status' => 'confirmed', 'lab_space_id' => $space2->id]);
        $booking = LabBooking::factory()->create([
            'booking_series_id' => $series->id,
            'lab_space_id' => $space2->id,
            'user_id' => $series->user_id,
            'status' => 'confirmed',
            'starts_at' => now()->addDays(1),
        ]);
        
        $supervisor = $this->createStaff('lab_supervisor');
        $supervisor->assignedLabSpaces()->attach($space1->id);

        $response = $this->authenticatedRequest(
            $supervisor,
            'POST',
            "/api/admin/bookings/series/{$series->id}/cancel",
            ['reason' => 'Unauthorized attempt']
        );

        $response->assertStatus(403);
    }

    public function test_lab_manager_can_initiate_refund_via_series_cancel(): void
    {
        $series = $this->createSeries(['status' => 'confirmed']);
        $booking = LabBooking::factory()->create([
            'booking_series_id' => $series->id,
            'lab_space_id' => $series->lab_space_id,
            'user_id' => $series->user_id,
            'status' => 'confirmed',
            'total_price' => 1000.00,
            'starts_at' => now()->addDays(1),
        ]);
        
        Payment::factory()->create([
            'payable_type' => 'lab_booking',
            'payable_id' => $booking->id,
            'amount' => 1000.00,
            'status' => 'paid',
        ]);
        
        $manager = $this->createStaff('lab_manager');

        $response = $this->authenticatedRequest(
            $manager,
            'POST',
            "/api/admin/bookings/series/{$series->id}/cancel",
            ['reason' => 'Full refund' ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('refund_initiated', true);
        $response->assertJsonPath('refund_status', 'pending');

        $this->assertDatabaseHas('refunds', [
            'refundable_type' => 'lab_booking',
            'refundable_id' => $booking->id,
            'status' => 'pending',
        ]);
    }

    public function test_lab_supervisor_refund_is_pending_approval(): void
    {
        $space = LabSpace::factory()->create();
        $series = $this->createSeries(['status' => 'confirmed', 'lab_space_id' => $space->id]);
        $booking = LabBooking::factory()->create([
            'booking_series_id' => $series->id,
            'lab_space_id' => $space->id,
            'user_id' => $series->user_id,
            'status' => 'confirmed',
            'total_price' => 1000.00,
            'starts_at' => now()->addDays(1),
        ]);
        
        Payment::factory()->create([
            'payable_type' => 'lab_booking',
            'payable_id' => $booking->id,
            'amount' => 1000.00,
            'status' => 'paid',
        ]);
        
        $supervisor = $this->createStaff('lab_supervisor');
        $supervisor->assignedLabSpaces()->attach($space->id);

        $response = $this->authenticatedRequest(
            $supervisor,
            'POST',
            "/api/admin/bookings/series/{$series->id}/cancel",
            ['reason' => 'Staff request']
        );

        $response->assertStatus(200);
        $response->assertJsonPath('refund_status', 'pending');

        $this->assertDatabaseHas('refunds', [
            'refundable_type' => 'lab_booking',
            'refundable_id' => $booking->id,
            'status' => 'pending',
        ]);
    }
}
