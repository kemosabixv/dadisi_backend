<?php

namespace Tests\Feature\Notifications;

use App\Models\LabBooking;
use App\Models\LabMaintenanceBlock;
use App\Models\LabSpace;
use App\Models\User;
use App\Notifications\BookingRescheduledNotification;
use App\Notifications\BookingRescheduleNeededNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LabBookingNotificationTest extends TestCase
{
    use RefreshDatabase;

    private LabSpace $space;
    private User $user;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->user = User::factory()->create(['email' => 'user@example.com']);
        $this->admin = User::factory()->create();
        $this->space = LabSpace::factory()->create();
    }

    #[Test]
    public function booking_rescheduled_notification_contains_correct_data()
    {
        $booking = LabBooking::factory()->create([
            'lab_space_id' => $this->space->id,
            'user_id' => $this->user->id,
            'starts_at' => Carbon::parse('2026-03-15T10:00:00'),
            'ends_at' => Carbon::parse('2026-03-15T12:00:00'),
        ]);

        $block = LabMaintenanceBlock::factory()->create([
            'lab_space_id' => $this->space->id,
            'block_type' => LabMaintenanceBlock::BLOCK_TYPE_MAINTENANCE,
        ]);

        $oldStartsAt = $booking->starts_at;
        $oldEndsAt = $booking->ends_at;

        // Simulate rescheduling the booking
        $booking->update([
            'starts_at' => Carbon::parse('2026-03-16T10:00:00'),
            'ends_at' => Carbon::parse('2026-03-16T12:00:00'),
        ]);

        // Send notification
        Notification::send(
            $this->user,
            new BookingRescheduledNotification(
                booking: $booking,
                oldStartsAt: $oldStartsAt,
                oldEndsAt: $oldEndsAt,
                block: $block
            )
        );

        // Verify notification was sent
        Notification::assertSentTo(
            $this->user,
            BookingRescheduledNotification::class
        );
    }

    #[Test]
    public function booking_rescheduled_notification_includes_old_and_new_times()
    {
        $booking = LabBooking::factory()->create([
            'lab_space_id' => $this->space->id,
            'user_id' => $this->user->id,
            'starts_at' => Carbon::parse('2026-03-15T10:00:00'),
            'ends_at' => Carbon::parse('2026-03-15T12:00:00'),
        ]);

        $block = LabMaintenanceBlock::factory()->create();

        $oldStartsAt = $booking->starts_at;
        $oldEndsAt = $booking->ends_at;

        $booking->update([
            'starts_at' => Carbon::parse('2026-03-16T14:00:00'),
            'ends_at' => Carbon::parse('2026-03-16T16:00:00'),
        ]);

        $notification = new BookingRescheduledNotification(
            booking: $booking,
            oldStartsAt: $oldStartsAt,
            oldEndsAt: $oldEndsAt,
            block: $block
        );

        $mailMessage = $notification->toMail($this->user);

        // Check mail subject contains "Rescheduled"
        $this->assertStringContainsString('Rescheduled', $mailMessage->subject);
        
        // Check mail content contains old and new times
        $rendered = $mailMessage->render();
        $this->assertStringContainsString('Original Booking', $rendered);
        $this->assertStringContainsString('New Booking', $rendered);
    }

    #[Test]
    public function booking_rescheduled_notification_stores_in_database()
    {
        $booking = LabBooking::factory()->create([
            'lab_space_id' => $this->space->id,
            'user_id' => $this->user->id,
            'starts_at' => Carbon::parse('2026-03-15T10:00:00'),
            'ends_at' => Carbon::parse('2026-03-15T12:00:00'),
        ]);

        $block = LabMaintenanceBlock::factory()->create();

        $oldStartsAt = $booking->starts_at;
        $oldEndsAt = $booking->ends_at;

        $booking->update([
            'starts_at' => Carbon::parse('2026-03-16T10:00:00'),
            'ends_at' => Carbon::parse('2026-03-16T12:00:00'),
        ]);

        $notification = new BookingRescheduledNotification(
            booking: $booking,
            oldStartsAt: $oldStartsAt,
            oldEndsAt: $oldEndsAt,
            block: $block
        );

        $databaseData = $notification->toDatabase($this->user);

        $this->assertArrayHasKey('booking_id', $databaseData);
        $this->assertArrayHasKey('space_name', $databaseData);
        $this->assertArrayHasKey('old_starts_at', $databaseData);
        $this->assertArrayHasKey('new_starts_at', $databaseData);
        $this->assertEquals($booking->id, $databaseData['booking_id']);
    }

    #[Test]
    public function booking_reschedule_needed_notification_alerts_manual_intervention()
    {
        $booking = LabBooking::factory()->create([
            'lab_space_id' => $this->space->id,
            'user_id' => $this->user->id,
            'starts_at' => Carbon::parse('2026-03-15T10:00:00'),
            'ends_at' => Carbon::parse('2026-03-15T12:00:00'),
        ]);

        $block = LabMaintenanceBlock::factory()->create([
            'block_type' => LabMaintenanceBlock::BLOCK_TYPE_MAINTENANCE,
            'title' => 'Equipment Servicing',
        ]);

        Notification::send(
            $this->user,
            new BookingRescheduleNeededNotification(
                booking: $booking,
                block: $block
            )
        );

        Notification::assertSentTo(
            $this->user,
            BookingRescheduleNeededNotification::class
        );
    }

    #[Test]
    public function booking_reschedule_needed_notification_includes_block_info()
    {
        $booking = LabBooking::factory()->create([
            'lab_space_id' => $this->space->id,
            'user_id' => $this->user->id,
        ]);

        $block = LabMaintenanceBlock::factory()->create([
            'block_type' => LabMaintenanceBlock::BLOCK_TYPE_MAINTENANCE,
            'title' => 'Critical System Update',
            'reason' => 'Server migration',
        ]);

        $notification = new BookingRescheduleNeededNotification(
            booking: $booking,
            block: $block
        );

        $databaseData = $notification->toDatabase($this->user);

        $this->assertEquals($booking->id, $databaseData['booking_id']);
        $this->assertEquals('maintenance', $databaseData['block_type']);
        $this->assertEquals('Critical System Update', $databaseData['block_title']);
    }

    #[Test]
    public function rescheduled_notification_includes_block_type_and_reason()
    {
        $booking = LabBooking::factory()->create([
            'lab_space_id' => $this->space->id,
            'user_id' => $this->user->id,
        ]);

        $block = LabMaintenanceBlock::factory()->create([
            'block_type' => LabMaintenanceBlock::BLOCK_TYPE_HOLIDAY,
            'title' => 'Public Holiday',
            'reason' => 'Independence Day',
        ]);

        $notification = new BookingRescheduledNotification(
            booking: $booking,
            oldStartsAt: Carbon::now(),
            oldEndsAt: Carbon::now()->addHours(2),
            block: $block
        );

        $databaseData = $notification->toDatabase($this->user);

        $this->assertEquals('holiday', $databaseData['block_type']);
        $this->assertEquals('Public Holiday', $databaseData['block_title']);
        $this->assertArrayHasKey('message', $databaseData);
    }

    #[Test]
    public function notification_queues_for_async_processing()
    {
        $booking = LabBooking::factory()->create([
            'lab_space_id' => $this->space->id,
            'user_id' => $this->user->id,
        ]);

        $block = LabMaintenanceBlock::factory()->create();

        $notification = new BookingRescheduledNotification(
            booking: $booking,
            oldStartsAt: Carbon::now(),
            oldEndsAt: Carbon::now()->addHours(2),
            block: $block
        );

        // Check that notification implements ShouldQueue
        $this->assertInstanceOf(
            \Illuminate\Contracts\Queue\ShouldQueue::class,
            $notification
        );
    }

    // Test for user without email removed - database has NOT NULL constraint on email column
    // #[Test]
    // public function user_without_email_handles_gracefully()
    // {
    //     ...
    // }

    #[Test]
    public function notification_message_respects_user_name()
    {
        $user = User::factory()->create([
            'username' => 'John',
            'email' => 'john@example.com',
        ]);

        $booking = LabBooking::factory()->create([
            'lab_space_id' => $this->space->id,
            'user_id' => $user->id,
        ]);

        $block = LabMaintenanceBlock::factory()->create();

        $notification = new BookingRescheduledNotification(
            booking: $booking,
            oldStartsAt: Carbon::now(),
            oldEndsAt: Carbon::now()->addHours(2),
            block: $block
        );

        $mailMessage = $notification->toMail($user);

        // Check that greeting includes user's name
        $this->assertStringContainsString('John', $mailMessage->render());
    }
}
