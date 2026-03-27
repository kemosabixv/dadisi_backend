<?php

namespace Tests\Unit\Services;

use App\Models\LabBooking;
use App\Models\LabMaintenanceBlock;
use App\Models\LabSpace;
use App\Models\User;
use App\Services\LabBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LabBookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private LabBookingService $service;
    private LabSpace $space;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LabBookingService::class);
        $this->user = User::factory()->create();
        $this->space = LabSpace::factory()->create([
            'hourly_rate' => 50,
        ]);
    }

    #[Test]
    public function it_generates_hourly_slots_for_a_single_day()
    {
        $date = Carbon::parse('2026-03-15');

        // Get slots for the date
        $availability = $this->service->getAvailabilityCalendar($this->space, $date, $date);

        $dateStr = $date->format('Y-m-d');
        $this->assertArrayHasKey($dateStr, $availability['events']);
        
        $dayData = $availability['events'][$dateStr];
        $this->assertArrayHasKey('hours', $dayData);
        
        $hours = $dayData['hours'];
        $this->assertCount(12, $hours); // 8 AM to 8 PM = 12 hours

        // Check first hour
        $firstHour = $hours[0];
        $this->assertEquals(8, $firstHour['hour']);
        $this->assertTrue($firstHour['available']);
        $this->assertEquals(0, $firstHour['booked']);
        $this->assertEquals('available', $firstHour['status']);

        // Check last hour
        $lastHour = $hours[11];
        $this->assertEquals(19, $lastHour['hour']);
        $this->assertTrue($lastHour['available']);
    }

    #[Test]
    public function it_marks_hours_as_booked_when_booking_exists()
    {
        $date = Carbon::parse('2026-03-15');

        // Create a booking for 10:00 - 12:00
        LabBooking::factory()->create([
            'lab_space_id' => $this->space->id,
            'user_id' => $this->user->id,
            'starts_at' => $date->copy()->setHour(10)->setMinute(0),
            'ends_at' => $date->copy()->setHour(12)->setMinute(0),
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        $availability = $this->service->getAvailabilityCalendar($this->space, $date, $date);
        $hours = $availability['events'][$date->format('Y-m-d')]['hours'];

        // 10:00 hour (index 2, since 8:00 is index 0)
        $hour10 = $hours[2];
        $this->assertEquals(10, $hour10['hour']);
        $this->assertEquals(1, $hour10['booked']);

        // 11:00 hour (index 3)
        $hour11 = $hours[3];
        $this->assertEquals(11, $hour11['hour']);
        $this->assertEquals(1, $hour11['booked']);

        // 9:00 hour (index 1) should not be booked
        $hour9 = $hours[1];
        $this->assertEquals(9, $hour9['hour']);
        $this->assertEquals(0, $hour9['booked']);
    }

    #[Test]
    public function it_marks_slots_as_full_when_blocckk_exists()
    {
        $date = Carbon::parse('2026-03-15');

        // Create a maintenance block from 14:00 - 16:00
        LabMaintenanceBlock::create([
            'lab_space_id' => $this->space->id,
            'block_type' => LabMaintenanceBlock::BLOCK_TYPE_MAINTENANCE,
            'title' => 'Equipment Servicing',
            'starts_at' => $date->copy()->setHour(14)->setMinute(0),
            'ends_at' => $date->copy()->setHour(16)->setMinute(0),
            'created_by' => $this->user->id,
        ]);

        $availability = $this->service->getAvailabilityCalendar($this->space, $date, $date);
        $hours = $availability['events'][$date->format('Y-m-d')]['hours'];

        // 14:00 hour (index 6)
        $hour14 = $hours[6];
        $this->assertEquals(14, $hour14['hour']);
        $this->assertFalse($hour14['available']);
        $this->assertEquals('full', $hour14['status']);

        // 15:00 hour (index 7)
        $hour15 = $hours[7];
        $this->assertEquals(15, $hour15['hour']);
        $this->assertFalse($hour15['available']);

        // 13:00 hour (index 5) should be available
        $hour13 = $hours[5];
        $this->assertTrue($hour13['available']);
    }

    #[Test]
    public function it_finds_alternative_slot_within_180_days()
    {
        $blockStart = Carbon::parse('2026-03-15T10:00:00');
        $blockEnd = Carbon::parse('2026-03-15T12:00:00');

        // Create a maintenance block
        $block = LabMaintenanceBlock::create([
            'lab_space_id' => $this->space->id,
            'block_type' => LabMaintenanceBlock::BLOCK_TYPE_MAINTENANCE,
            'title' => 'Equipment Servicing',
            'starts_at' => $blockStart,
            'ends_at' => $blockEnd,
            'created_by' => $this->user->id,
        ]);

        // Find alternative slot for a 2-hour booking
        $alternativeSlot = $this->service->findAlternativeSlot(
            spaceId: $this->space->id,
            durationHours: 2,
            blockToAvoid: $block
        );

        // Should find a slot after the block
        $this->assertNotNull($alternativeSlot);
        $this->assertArrayHasKey('starts_at', $alternativeSlot);
        $this->assertArrayHasKey('ends_at', $alternativeSlot);

        $startTime = Carbon::parse($alternativeSlot['starts_at']);
        $endTime = Carbon::parse($alternativeSlot['ends_at']);

        // Slot should be after block end
        $this->assertTrue($startTime->greaterThanOrEqualTo($blockEnd));

        // Duration should be 2 hours
        $this->assertEquals(2, $startTime->diffInHours($endTime));
    }

    #[Test]
    public function it_skips_blocked_days_when_finding_alternative()
    {
        $blockStart = Carbon::parse('2026-03-15T08:00:00');
        $blockEnd = Carbon::parse('2026-03-15T20:00:00'); // Full day block

        $block = LabMaintenanceBlock::create([
            'lab_space_id' => $this->space->id,
            'block_type' => LabMaintenanceBlock::BLOCK_TYPE_MAINTENANCE,
            'title' => 'Full day maintenance',
            'starts_at' => $blockStart,
            'ends_at' => $blockEnd,
            'created_by' => $this->user->id,
        ]);

        // Create another block on the next day
        $secondBlockStart = Carbon::parse('2026-03-16T08:00:00');
        $secondBlockEnd = Carbon::parse('2026-03-16T20:00:00');

        LabMaintenanceBlock::create([
            'lab_space_id' => $this->space->id,
            'block_type' => LabMaintenanceBlock::BLOCK_TYPE_MAINTENANCE,
            'title' => 'Second day maintenance',
            'starts_at' => $secondBlockStart,
            'ends_at' => $secondBlockEnd,
            'created_by' => $this->user->id,
        ]);

        // Find alternative slot for 2-hour booking
        $alternativeSlot = $this->service->findAlternativeSlot(
            spaceId: $this->space->id,
            durationHours: 2,
            blockToAvoid: $block
        );

        // Should skip to 2026-03-17 or later
        $startTime = Carbon::parse($alternativeSlot['starts_at']);
        $this->assertGreaterThanOrEqual(
            Carbon::parse('2026-03-17'),
            $startTime->startOfDay()
        );
    }

    #[Test]
    public function it_returns_null_if_no_slot_found_within_180_days()
    {
        // Fill 180 days with blocks
        $blockStart = Carbon::parse('2026-03-15T08:00:00');
        
        for ($i = 0; $i < 180; $i++) {
            LabMaintenanceBlock::create([
                'lab_space_id' => $this->space->id,
                'block_type' => LabMaintenanceBlock::BLOCK_TYPE_CLOSURE,
                'title' => "Block day $i",
                'starts_at' => $blockStart->copy()->addDays($i),
                'ends_at' => $blockStart->copy()->addDays($i)->addHours(12),
                'created_by' => $this->user->id,
            ]);
        }

        // Try to find a slot
        $firstBlock = LabMaintenanceBlock::first();
        $alternativeSlot = $this->service->findAlternativeSlot(
            spaceId: $this->space->id,
            durationHours: 2,
            blockToAvoid: $firstBlock
        );

        // Should return null because no slots available
        $this->assertNull($alternativeSlot);
    }

    #[Test]
    public function it_handles_multiple_bookings_on_same_hour()
    {
        $date = Carbon::parse('2026-03-15');

        // Create 2 overlapping bookings at same time
        for ($i = 0; $i < 2; $i++) {
            LabBooking::factory()->create([
                'lab_space_id' => $this->space->id,
                'user_id' => User::factory()->create()->id,
                'starts_at' => $date->copy()->setHour(10)->setMinute(0),
                'ends_at' => $date->copy()->setHour(12)->setMinute(0),
                'status' => LabBooking::STATUS_CONFIRMED,
            ]);
        }

        $availability = $this->service->getAvailabilityCalendar($this->space, $date, $date);
        $hours = $availability['events'][$date->format('Y-m-d')]['hours'];

        // 10:00 hour should show 2 bookings
        $hour10 = $hours[2];
        $this->assertEquals(2, $hour10['booked']);
    }

    #[Test]
    public function it_excludes_cancelled_bookings_from_availability()
    {
        $date = Carbon::parse('2026-03-15');

        // Create a confirmed booking
        LabBooking::factory()->create([
            'lab_space_id' => $this->space->id,
            'user_id' => $this->user->id,
            'starts_at' => $date->copy()->setHour(10)->setMinute(0),
            'ends_at' => $date->copy()->setHour(12)->setMinute(0),
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        // Create a cancelled booking at same time
        LabBooking::factory()->create([
            'lab_space_id' => $this->space->id,
            'user_id' => $this->user->id,
            'starts_at' => $date->copy()->setHour(10)->setMinute(0),
            'ends_at' => $date->copy()->setHour(12)->setMinute(0),
            'status' => LabBooking::STATUS_CANCELLED,
        ]);

        $availability = $this->service->getAvailabilityCalendar($this->space, $date, $date);
        $hours = $availability['events'][$date->format('Y-m-d')]['hours'];

        // Should only count the confirmed booking, not the cancelled one
        $hour10 = $hours[2];
        $this->assertEquals(1, $hour10['booked']);
    }

    #[Test]
    public function it_provides_date_range_availability()
    {
        $startDate = Carbon::parse('2026-03-15');
        $endDate = Carbon::parse('2026-03-17');

        $availability = $this->service->getAvailabilityCalendar($this->space, $startDate, $endDate);

        // Should have 3 days of data
        $this->assertCount(3, $availability['events']);
        $this->assertArrayHasKey('2026-03-15', $availability['events']);
        $this->assertArrayHasKey('2026-03-16', $availability['events']);
        $this->assertArrayHasKey('2026-03-17', $availability['events']);
    }

    #[Test]
    public function it_respects_exclude_booking_id_parameter()
    {
        $date = Carbon::parse('2026-03-15');

        // Create original booking
        $originalBooking = LabBooking::factory()->create([
            'lab_space_id' => $this->space->id,
            'user_id' => $this->user->id,
            'starts_at' => $date->copy()->setHour(10)->setMinute(0),
            'ends_at' => $date->copy()->setHour(12)->setMinute(0),
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        // Create a block
        $block = LabMaintenanceBlock::create([
            'lab_space_id' => $this->space->id,
            'block_type' => LabMaintenanceBlock::BLOCK_TYPE_MAINTENANCE,
            'title' => 'Maintenance',
            'starts_at' => $date->copy()->setHour(10)->setMinute(0),
            'ends_at' => $date->copy()->setHour(20)->setMinute(0),
            'created_by' => $this->user->id,
        ]);

        // Find alternative, excluding the original booking
        $alternativeSlot = $this->service->findAlternativeSlot(
            spaceId: $this->space->id,
            durationHours: 2,
            blockToAvoid: $block,
            excludeBookingId: $originalBooking->id
        );

        // Should find a slot (not checking original booking overlap)
        $this->assertNotNull($alternativeSlot);
    }
}
