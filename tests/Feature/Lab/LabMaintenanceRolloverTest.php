<?php

namespace Tests\Feature\Lab;

use App\Models\LabBooking;
use App\Models\LabMaintenanceBlock;
use App\Models\LabSpace;
use App\Models\MaintenanceBlockRollover;
use App\Models\User;
use App\Notifications\BookingRescheduledNotification;
use App\Notifications\BookingRescheduleNeededNotification;
use App\Notifications\BookingRolloverEscalatedNotification;
use App\Services\Contracts\LabBookingServiceContract;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class LabMaintenanceRolloverTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $user;
    protected $labSpace;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Roles and Permissions
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        // Create admin and user
        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');
        
        $this->user = User::factory()->create();
        
        // Create lab space with operating hours
        $this->labSpace = LabSpace::factory()->create([
            'capacity' => 10,
            'opens_at' => '08:00',
            'closes_at' => '20:00',
            'operating_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ]);

        $this->service = app(LabBookingServiceContract::class);
    }

    #[Test]
    public function it_successfully_rolls_over_booking_to_next_available_slot()
    {
        Notification::fake();

        // 1. Create a booking on Monday 10 AM - 12 PM
        $nextMonday = Carbon::now()->next(Carbon::MONDAY)->setHour(10)->startOfHour();
        $booking = LabBooking::factory()->create([
            'user_id' => $this->user->id,
            'lab_space_id' => $this->labSpace->id,
            'status' => LabBooking::STATUS_CONFIRMED,
            'starts_at' => $nextMonday,
            'ends_at' => $nextMonday->copy()->addHours(2),
        ]);

        // 2. Create a conflicting maintenance block
        $block = LabMaintenanceBlock::factory()->create([
            'lab_space_id' => $this->labSpace->id,
            'title' => 'Emergency Repair',
            'block_type' => 'maintenance',
            'starts_at' => $nextMonday->copy()->subHour(),
            'ends_at' => $nextMonday->copy()->addHours(3),
            'created_by' => $this->admin->id,
        ]);

        // 3. Execute rollover
        $this->service->rollOverBookings($block);

        // 4. Assertions
        $booking->refresh();
        
        // Original booking should NOT be cancelled, but rescheduled
        $this->assertEquals(LabBooking::STATUS_CONFIRMED, $booking->status);
        $this->assertTrue($booking->starts_at->isAfter($nextMonday));
        
        // Check audit log
        $this->assertDatabaseHas('maintenance_block_rollovers', [
            'maintenance_block_id' => $block->id,
            'original_booking_id' => $booking->id,
            'status' => MaintenanceBlockRollover::STATUS_ROLLED_OVER,
        ]);

        Notification::assertSentTo($this->user, BookingRescheduledNotification::class);
    }

    #[Test]
    public function it_sets_pending_user_resolution_when_auto_rollover_fails()
    {
        Notification::fake();

        // 1. Fully book the lab for the next 7 days (or just mock failure)
        // Here we'll just verify the logic path if findAlternativeSlot returns null
        // To force failure without creating 100 bookings, we could mock the service
        // but let's try a real scenario with a very narrow operating window
        
        $this->labSpace->update([
            'opens_at' => '10:00',
            'closes_at' => '11:00',
            'operating_days' => ['monday'],
        ]);

        $nextMonday = Carbon::now()->next(Carbon::MONDAY)->setHour(10)->startOfHour();
        $booking = LabBooking::factory()->create([
            'user_id' => $this->user->id,
            'lab_space_id' => $this->labSpace->id,
            'status' => LabBooking::STATUS_CONFIRMED,
            'starts_at' => $nextMonday,
            'ends_at' => $nextMonday->copy()->addHours(2), // Too long for the window
        ]);

        $block = LabMaintenanceBlock::factory()->create([
            'lab_space_id' => $this->labSpace->id,
            'title' => 'Total Closure',
            'block_type' => 'closure',
            'starts_at' => $nextMonday->copy(),
            'ends_at' => $nextMonday->copy()->addHours(4),
            'created_by' => $this->admin->id,
        ]);

        $this->service->rollOverBookings($block);

        $booking->refresh();
        $this->assertEquals(LabBooking::STATUS_PENDING_USER_RESOLUTION, $booking->status);

        $this->assertDatabaseHas('maintenance_block_rollovers', [
            'original_booking_id' => $booking->id,
            'status' => MaintenanceBlockRollover::STATUS_PENDING_USER,
        ]);

        Notification::assertSentTo($this->user, BookingRescheduleNeededNotification::class);
    }

    #[Test]
    public function user_can_resolve_conflict_manually()
    {
        // 1. Setup a booking in pending_user_resolution
        $booking = LabBooking::factory()->create([
            'user_id' => $this->user->id,
            'lab_space_id' => $this->labSpace->id,
            'status' => LabBooking::STATUS_PENDING_USER_RESOLUTION,
            'starts_at' => Carbon::now()->addDays(1),
            'ends_at' => Carbon::now()->addDays(1)->addHours(2),
        ]);

        $newStart = Carbon::now()->addDays(2)->setHour(10)->startOfHour();
        $newEnd = $newStart->copy()->addHours(2);

        $this->actingAs($this->user);
        $response = $this->postJson("/api/bookings/{$booking->id}/resolve-conflict", [
            'starts_at' => $newStart->toIso8601String(),
            'ends_at' => $newEnd->toIso8601String(),
        ]);

        $response->assertStatus(200);
        $booking->refresh();
        $this->assertEquals(LabBooking::STATUS_CONFIRMED, $booking->status);
        $this->assertEquals($newStart->toDateTimeString(), $booking->starts_at->toDateTimeString());
    }

    #[Test]
    public function it_escalates_to_staff_after_48_hours()
    {
        Notification::fake();

        // 1. Create a rollover in pending_user status from 3 days ago
        $rollover = MaintenanceBlockRollover::factory()->create([
            'status' => MaintenanceBlockRollover::STATUS_PENDING_USER,
            'updated_at' => Carbon::now()->subDays(3),
        ]);

        // 2. Run escalation command
        $this->artisan('lab:escalate-rollovers')->assertExitCode(0);

        // 3. Assertions
        $rollover->refresh();
        $this->assertEquals(MaintenanceBlockRollover::STATUS_ESCALATED, $rollover->status);

        // Check notification to admins (using one created in setUp or locally)
        Notification::assertSentTo($this->admin, BookingRolloverEscalatedNotification::class);
    }
}
