<?php

namespace Tests\Feature\Lab;

use App\Jobs\MarkLabAttendanceJob;
use App\Jobs\MarkLabNoShowsJob;
use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;


class LabAttendanceLogicTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $staff;
    protected $labSpace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        
        // 1. Setup Roles and Permissions
        $staffRole = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        $supervisorRole = Role::firstOrCreate(['name' => 'lab_supervisor', 'guard_name' => 'web']);
        $permission = Permission::firstOrCreate(['name' => 'mark_lab_attendance', 'guard_name' => 'web']);
        
        $staffRole->givePermissionTo($permission);
        $supervisorRole->givePermissionTo($permission);

        $this->staff = User::factory()->create();
        $this->staff->assignRole($staffRole);
        $this->staff->assignRole($supervisorRole);

        // 2. Setup Lab and Assignment
        $this->labSpace = LabSpace::factory()->create(['capacity' => 10]);
        
        // Assign staff as supervisor to this lab
        $this->staff->assignedLabSpaces()->attach($this->labSpace->id, ['assigned_at' => now()]);
    }

    #[Test]
    public function user_can_self_check_in_within_15_minute_grace_period()
    {
        // 1. Create a confirmed booking starting in 10 minutes
        $booking = LabBooking::factory()->create([
            'user_id' => $this->user->id,
            'lab_space_id' => $this->labSpace->id,
            'status' => LabBooking::STATUS_CONFIRMED,
            'starts_at' => now()->addMinutes(10),
            'ends_at' => now()->addMinutes(70),
        ]);

        // 2. Mock the request to check-in (using the same logic as the QR code scan)
        $this->actingAs($this->user);
        
        // This simulates scanning the lab QR code
        $response = $this->postJson(route('lab-bookings.check-in-by-token'), [
            'token' => $this->labSpace->checkin_token,
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($booking->refresh()->checked_in_at);
    }

    #[Test]
    public function supervisor_can_check_in_guest_booking()
    {
        // 1. Create a guest booking
        $booking = LabBooking::factory()->create([
            'user_id' => $this->staff->id, // Staff created it for guest
            'lab_space_id' => $this->labSpace->id,
            'status' => LabBooking::STATUS_CONFIRMED,
            'guest_name' => 'John Guest',
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->addMinutes(55),
        ]);

        $this->actingAs($this->staff);
        
        $response = $this->postJson("/api/admin/lab-bookings/{$booking->id}/check-in");

        $response->assertStatus(200);
        $this->assertNotNull($booking->refresh()->checked_in_at);
    }

    #[Test]
    public function mark_attendance_job_finalizes_checked_in_bookings_after_end()
    {
        // 1. Create a booking that ended 20 minutes ago and WAS checked in
        $booking = LabBooking::factory()->create([
            'user_id' => $this->user->id,
            'lab_space_id' => $this->labSpace->id,
            'status' => LabBooking::STATUS_CONFIRMED,
            'starts_at' => now()->subMinutes(80),
            'ends_at' => now()->subMinutes(20),
            'checked_in_at' => now()->subMinutes(75),
        ]);

        // 2. Run the job
        (new MarkLabAttendanceJob())->handle();

        // 3. Verify it is completed
        $this->assertEquals(LabBooking::STATUS_COMPLETED, $booking->refresh()->status);
    }

    #[Test]
    public function mark_attendance_job_ignores_bookings_without_check_in()
    {
        // 1. Create a booking that ended 20 minutes ago but was NOT checked in
        $booking = LabBooking::factory()->create([
            'user_id' => $this->user->id,
            'lab_space_id' => $this->labSpace->id,
            'status' => LabBooking::STATUS_CONFIRMED,
            'starts_at' => now()->subMinutes(80),
            'ends_at' => now()->subMinutes(20),
            'checked_in_at' => null,
        ]);

        // 2. Run the job
        (new MarkLabAttendanceJob())->handle();

        // 3. Verify it is still confirmed (it should be picked up by NoShow job instead)
        $this->assertEquals(LabBooking::STATUS_CONFIRMED, $booking->refresh()->status);
    }

    #[Test]
    public function mark_no_show_job_handles_missed_bookings()
    {
        // 1. Create a booking that ended 20 minutes ago and was NOT checked in
        $booking = LabBooking::factory()->create([
            'user_id' => $this->user->id,
            'lab_space_id' => $this->labSpace->id,
            'status' => LabBooking::STATUS_CONFIRMED,
            'starts_at' => now()->subMinutes(80),
            'ends_at' => now()->subMinutes(20),
            'checked_in_at' => null,
            'total_price' => 10,
        ]);

        // 2. Run the job
        app(MarkLabNoShowsJob::class)->handle(app(\App\Services\Contracts\LabBookingServiceContract::class));

        // 3. Verify it is no_show
        $this->assertEquals(LabBooking::STATUS_NO_SHOW, $booking->refresh()->status);
    }

    #[Test]
    public function check_out_route_is_removed()
    {
        $booking = LabBooking::factory()->create([
            'user_id' => $this->user->id,
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        $this->actingAs($this->staff);
        
        // This route should no longer exist (returns 405 Method Not Allowed if parent route exists, or 404)
        $response = $this->postJson("/api/admin/lab-bookings/{$booking->id}/check-out");

        $this->assertTrue(in_array($response->status(), [404, 405]), "Expected 404 or 405 but received {$response->status()}");
    }

    #[Test]
    public function user_cannot_check_in_too_early()
    {
        $now = Carbon::parse('2025-01-01 10:00:00');
        Carbon::setTestNow($now);

        // Create a booking starting in 20 minutes (> 15 min allowance)
        LabBooking::factory()->create([
            'user_id' => $this->user->id,
            'lab_space_id' => $this->labSpace->id,
            'status' => LabBooking::STATUS_CONFIRMED,
            'starts_at' => $now->copy()->addMinutes(20),
            'ends_at' => $now->copy()->addMinutes(80),
        ]);

        $this->actingAs($this->user);
        
        $response = $this->postJson(route('lab-bookings.check-in-by-token'), [
            'token' => $this->labSpace->checkin_token,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'No active booking found for you in this lab space right now.');
        
        Carbon::setTestNow(); // Reset
    }

    #[Test]
    public function user_cannot_check_in_after_slot_ends()
    {
        $now = Carbon::parse('2025-01-01 10:00:00');
        Carbon::setTestNow($now);

        // Create a booking that ended 1 minute ago
        LabBooking::factory()->create([
            'user_id' => $this->user->id,
            'lab_space_id' => $this->labSpace->id,
            'status' => LabBooking::STATUS_CONFIRMED,
            'starts_at' => $now->copy()->subMinutes(61),
            'ends_at' => $now->copy()->subMinutes(1),
        ]);

        $this->actingAs($this->user);
        
        $response = $this->postJson(route('lab-bookings.check-in-by-token'), [
            'token' => $this->labSpace->checkin_token,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'No active booking found for you in this lab space right now.');
        
        Carbon::setTestNow(); // Reset
    }
}
