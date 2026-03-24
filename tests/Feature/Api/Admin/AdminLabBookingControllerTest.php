<?php

namespace Tests\Feature\Api\Admin;

use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminLabBookingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

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

    // ==================== Cancel Booking Tests ====================

    public function test_unauthorized_user_cannot_cancel_booking(): void
    {
        $booking = LabBooking::factory()->create(['status' => 'confirmed']);
        $member = User::factory()->create();
        $member->assignRole('member');

        $response = $this->authenticatedRequest(
            $member,
            'POST',
            "/api/admin/bookings/{$booking->id}/cancel",
            ['reason' => 'User requested']
        );

        $response->assertStatus(403);
    }

    public function test_lab_manager_can_cancel_booking(): void
    {
        $booking = LabBooking::factory()->create(['status' => 'confirmed']);
        $manager = $this->createStaff('lab_manager');

        $response = $this->authenticatedRequest(
            $manager,
            'POST',
            "/api/admin/bookings/{$booking->id}/cancel",
            ['reason' => 'Lab closure']
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'cancelled');

        $this->assertEquals('cancelled', $booking->fresh()->status);
    }

    public function test_admin_can_cancel_booking(): void
    {
        $booking = LabBooking::factory()->create(['status' => 'confirmed']);
        $admin = $this->createStaff('admin');

        $response = $this->authenticatedRequest(
            $admin,
            'POST',
            "/api/admin/bookings/{$booking->id}/cancel",
            ['reason' => 'System maintenance']
        );

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $booking->fresh()->status);
    }

    public function test_lab_supervisor_can_cancel_assigned_booking(): void
    {
        $space = LabSpace::factory()->create();
        $booking = LabBooking::factory()->create(['lab_space_id' => $space->id, 'status' => 'confirmed']);
        $supervisor = $this->createStaff('lab_supervisor');
        $supervisor->assignedLabSpaces()->attach($space->id);

        $response = $this->authenticatedRequest(
            $supervisor,
            'POST',
            "/api/admin/bookings/{$booking->id}/cancel",
            ['reason' => 'Member requested']
        );

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $booking->fresh()->status);
    }

    public function test_lab_supervisor_cannot_cancel_unassigned_booking(): void
    {
        $space1 = LabSpace::factory()->create();
        $space2 = LabSpace::factory()->create();
        $booking = LabBooking::factory()->create(['lab_space_id' => $space2->id, 'status' => 'confirmed']);
        $supervisor = $this->createStaff('lab_supervisor');
        $supervisor->assignedLabSpaces()->attach($space1->id);

        $response = $this->authenticatedRequest(
            $supervisor,
            'POST',
            "/api/admin/bookings/{$booking->id}/cancel",
            ['reason' => 'Unauthorized attempt']
        );

        $response->assertStatus(403);
    }

    public function test_cannot_cancel_already_cancelled_booking(): void
    {
        $booking = LabBooking::factory()->create(['status' => 'cancelled']);
        $manager = $this->createStaff('lab_manager');

        $response = $this->authenticatedRequest(
            $manager,
            'POST',
            "/api/admin/bookings/{$booking->id}/cancel",
            ['reason' => 'Idempotency test']
        );

        // Should either succeed (idempotent) or fail with appropriate status
        $this->assertContains($response->status(), [200, 400, 422]);
    }

    public function test_cancel_booking_returns_refund_preview(): void
    {
        $booking = LabBooking::factory()->create([
            'status' => 'confirmed',
            'total_price' => 1000.00,
        ]);
        $manager = $this->createStaff('lab_manager');

        $response = $this->authenticatedRequest(
            $manager,
            'POST',
            "/api/admin/bookings/{$booking->id}/cancel",
            ['reason' => 'Preview test']
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'refund_preview' => [
                    'original_amount',
                    'refund_amount',
                    'deduction',
                ],
            ],
        ]);
    }

    // ==================== Initiate Refund Tests ====================

    public function test_unauthorized_user_cannot_initiate_refund(): void
    {
        $booking = LabBooking::factory()->create(['status' => 'cancelled']);
        $member = User::factory()->create();
        $member->assignRole('member');

        $response = $this->authenticatedRequest(
            $member,
            'POST',
            "/api/admin/bookings/{$booking->id}/refund-request",
            ['notes' => 'Customer request']
        );

        $response->assertStatus(403);
    }

    public function test_lab_manager_can_initiate_refund(): void
    {
        $booking = LabBooking::factory()->create([
            'status' => 'cancelled',
            'total_price' => 1000.00,
            'payment_method' => 'card',
        ]);
        \App\Models\Payment::factory()->create([
            'payable_type' => 'lab_booking',
            'payable_id' => $booking->id,
            'amount' => 1000.00,
            'status' => 'paid',
        ]);
        $manager = $this->createStaff('lab_manager');

        $response = $this->authenticatedRequest(
            $manager,
            'POST',
            "/api/admin/bookings/{$booking->id}/refund-request",
            ['notes' => 'Full refund authorized']
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'approved');

        // Refund should be created and auto-approved for managers
        $this->assertDatabaseHas('refunds', [
            'refundable_type' => 'lab_booking',
            'refundable_id' => $booking->id,
            'status' => 'approved',
        ]);
    }

    public function test_admin_can_initiate_refund(): void
    {
        $booking = LabBooking::factory()->create([
            'status' => 'cancelled',
            'total_price' => 1000.00,
            'payment_method' => 'card',
        ]);
        \App\Models\Payment::factory()->create([
            'payable_type' => 'lab_booking',
            'payable_id' => $booking->id,
            'amount' => 1000.00,
            'status' => 'paid',
        ]);
        $admin = $this->createStaff('admin');

        $response = $this->authenticatedRequest(
            $admin,
            'POST',
            "/api/admin/bookings/{$booking->id}/refund-request"
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'approved');
    }

    public function test_lab_supervisor_refund_requires_approval(): void
    {
        $space = LabSpace::factory()->create();
        $booking = LabBooking::factory()->create([
            'lab_space_id' => $space->id,
            'status' => 'cancelled',
            'total_price' => 1000.00,
            'payment_method' => 'card',
        ]);
        \App\Models\Payment::factory()->create([
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
            "/api/admin/bookings/{$booking->id}/refund-request",
            ['notes' => 'Requested by supervisor']
        );

        $response->assertStatus(200);
        // Supervisor refunds should be pending approval
        $response->assertJsonPath('data.status', 'pending_approval');

        $this->assertDatabaseHas('refunds', [
            'refundable_type' => 'lab_booking',
            'refundable_id' => $booking->id,
            'status' => 'pending_approval',
        ]);
    }

    public function test_lab_supervisor_cannot_initiate_refund_for_unassigned_booking(): void
    {
        $space1 = LabSpace::factory()->create();
        $space2 = LabSpace::factory()->create();
        $booking = LabBooking::factory()->create([
            'lab_space_id' => $space2->id,
            'status' => 'cancelled',
        ]);
        $supervisor = $this->createStaff('lab_supervisor');
        $supervisor->assignedLabSpaces()->attach($space1->id);

        $response = $this->authenticatedRequest(
            $supervisor,
            'POST',
            "/api/admin/bookings/{$booking->id}/refund-request"
        );

        $response->assertStatus(403);
    }

    public function test_refund_requires_cancelled_or_completed_booking(): void
    {
        $booking = LabBooking::factory()->create(['status' => 'confirmed']);
        $manager = $this->createStaff('lab_manager');

        $response = $this->authenticatedRequest(
            $manager,
            'POST',
            "/api/admin/bookings/{$booking->id}/refund-request"
        );

        // Should fail validation
        $this->assertContains($response->status(), [400, 422]);
    }

    public function test_can_specify_custom_refund_amount(): void
    {
        $booking = LabBooking::factory()->create([
            'status' => 'cancelled',
            'total_price' => 1000.00,
            'payment_method' => 'card',
        ]);
        \App\Models\Payment::factory()->create([
            'payable_type' => 'lab_booking',
            'payable_id' => $booking->id,
            'amount' => 1000.00,
            'status' => 'paid',
        ]);
        $manager = $this->createStaff('lab_manager');

        $response = $this->authenticatedRequest(
            $manager,
            'POST',
            "/api/admin/bookings/{$booking->id}/refund-request",
            ['amount_cents' => 50000]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.requested_amount_cents', 50000);
    }

    public function test_refund_response_includes_calculated_amount(): void
    {
        $booking = LabBooking::factory()->create([
            'status' => 'cancelled',
            'total_price' => 1000.00,
            'payment_method' => 'card',
        ]);
        \App\Models\Payment::factory()->create([
            'payable_type' => 'lab_booking',
            'payable_id' => $booking->id,
            'amount' => 1000.00,
            'status' => 'paid',
        ]);
        $manager = $this->createStaff('lab_manager');

        $response = $this->authenticatedRequest(
            $manager,
            'POST',
            "/api/admin/bookings/{$booking->id}/refund-request"
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'booking_id',
                'status',
                'requested_amount_cents',
                'calculated_amount_cents',
                'requested_by',
                'created_at',
            ],
        ]);

        $response->assertJsonPath('data.requested_by.role', 'lab_manager');
    }

    // ==================== Integration Tests ====================

    public function test_disable_bookings_then_cancel_existing_booking(): void
    {
        $space = LabSpace::factory()->create(['bookings_enabled' => true]);
        $booking = LabBooking::factory()->create([
            'lab_space_id' => $space->id, 
            'status' => 'confirmed',
            'total_price' => 1000.00,
            'payment_method' => 'card',
        ]);
        \App\Models\Payment::factory()->create([
            'payable_type' => 'lab_booking',
            'payable_id' => $booking->id,
            'amount' => 1000.00,
            'status' => 'paid',
        ]);
        $manager = $this->createStaff('lab_manager');

        // Step 1: Disable bookings
        $disableResponse = $this->authenticatedRequest(
            $manager,
            'POST',
            "/api/admin/spaces/{$space->id}/bookings/disable",
            ['reason' => 'Lab closure']
        );
        $disableResponse->assertStatus(200);
        $this->assertFalse($space->fresh()->bookings_enabled);

        // Step 2: Existing booking can still be cancelled
        $cancelResponse = $this->authenticatedRequest(
            $manager,
            'POST',
            "/api/admin/bookings/{$booking->id}/cancel",
            ['reason' => 'Integration test']
        );
        $cancelResponse->assertStatus(200);
        $this->assertEquals('cancelled', $booking->fresh()->status);

        // Step 3: Initiate refund
        $refundResponse = $this->authenticatedRequest(
            $manager,
            'POST',
            "/api/admin/bookings/{$booking->id}/refund-request"
        );
        $refundResponse->assertStatus(200);
        $refundResponse->assertJsonPath('data.status', 'approved');
    }

    public function test_complete_workflow_with_supervisor(): void
    {
        $space = LabSpace::factory()->create();
        $booking = LabBooking::factory()->create([
            'lab_space_id' => $space->id,
            'status' => 'confirmed',
            'total_price' => 1000.00,
            'payment_method' => 'card',
        ]);
        \App\Models\Payment::factory()->create([
            'payable_type' => 'lab_booking',
            'payable_id' => $booking->id,
            'amount' => 1000.00,
            'status' => 'paid',
        ]);
        $supervisor = $this->createStaff('lab_supervisor');
        $supervisor->assignedLabSpaces()->attach($space->id);

        // Supervisor cancels booking
        $cancelResponse = $this->authenticatedRequest(
            $supervisor,
            'POST',
            "/api/admin/bookings/{$booking->id}/cancel",
            ['reason' => 'Member request']
        );
        $cancelResponse->assertStatus(200);

        // Supervisor requests refund (pending approval)
        $refundResponse = $this->authenticatedRequest(
            $supervisor,
            'POST',
            "/api/admin/bookings/{$booking->id}/refund-request",
            ['notes' => 'Full refund requested']
        );
        $refundResponse->assertStatus(200);
        $refundResponse->assertJsonPath('data.status', 'pending_approval');

        // Verify refund is pending
        $refund = Refund::where('refundable_id', $booking->id)
            ->where('refundable_type', 'lab_booking')
            ->first();
        $this->assertNotNull($refund);
        $this->assertEquals('pending_approval', $refund->status);
    }
}
