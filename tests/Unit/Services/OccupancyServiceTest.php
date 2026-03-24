<?php

namespace Tests\Unit\Services;

use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Services\OccupancyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OccupancyServiceTest extends TestCase
{
    use RefreshDatabase;
    private OccupancyService $occupancyService;
    private LabSpace $labSpace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->occupancyService = app(OccupancyService::class);

        // Create a test lab space with flexible capacity
        $this->labSpace = LabSpace::factory()->create([
            'slots_per_hour' => 2,  // 2 slots per hour
            'capacity' => 10,       // 10 people capacity (shouldn't be used for occupancy)
            'opens_at' => '09:00',
            'closes_at' => '17:00',
            'operating_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
        ]);
    }

    #[Test]
    public function it_calculates_occupancy_correctly_when_no_bookings()
    {
        $slotStart = Carbon::now()->setHour(10)->setMinute(0)->setSecond(0);
        $slotEnd = $slotStart->copy()->addHour();

        $occupancy = $this->occupancyService->getSlotOccupancy($this->labSpace, $slotStart, $slotEnd);

        $this->assertEquals(0, $occupancy['current']);
        $this->assertEquals(2, $occupancy['capacity']);
        $this->assertEquals(2, $occupancy['available']);
        $this->assertEquals(0, $occupancy['percentage']);
        $this->assertFalse($occupancy['is_full']);
        $this->assertFalse($occupancy['is_near_full']);
    }

    #[Test]
    public function it_counts_confirmed_bookings()
    {
        $slotStart = Carbon::now()->setHour(10)->setMinute(0)->setSecond(0);
        $slotEnd = $slotStart->copy()->addHour();

        // Create a confirmed booking in this slot
        LabBooking::factory()->create([
            'lab_space_id' => $this->labSpace->id,
            'starts_at' => $slotStart,
            'ends_at' => $slotEnd,
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        $occupancy = $this->occupancyService->getSlotOccupancy($this->labSpace, $slotStart, $slotEnd);

        $this->assertEquals(1, $occupancy['current']);
        $this->assertEquals(2, $occupancy['capacity']);
        $this->assertEquals(1, $occupancy['available']);
        $this->assertEquals(50, $occupancy['percentage']);
        $this->assertFalse($occupancy['is_full']);
        $this->assertFalse($occupancy['is_near_full']);
    }

    #[Test]
    public function it_counts_completed_bookings()
    {
        $slotStart = Carbon::now()->setHour(10)->setMinute(0)->setSecond(0);
        $slotEnd = $slotStart->copy()->addHour();

        // Create a completed booking in this slot
        LabBooking::factory()->create([
            'lab_space_id' => $this->labSpace->id,
            'starts_at' => $slotStart,
            'ends_at' => $slotEnd,
            'status' => LabBooking::STATUS_COMPLETED,
        ]);

        $occupancy = $this->occupancyService->getSlotOccupancy($this->labSpace, $slotStart, $slotEnd);

        $this->assertEquals(1, $occupancy['current']);
        $this->assertEquals(50, $occupancy['percentage']);
    }

    #[Test]
    public function it_ignores_pending_bookings()
    {
        $slotStart = Carbon::now()->setHour(10)->setMinute(0)->setSecond(0);
        $slotEnd = $slotStart->copy()->addHour();

        // Create a pending booking (should not count)
        LabBooking::factory()->create([
            'lab_space_id' => $this->labSpace->id,
            'starts_at' => $slotStart,
            'ends_at' => $slotEnd,
            'status' => LabBooking::STATUS_PENDING,
        ]);

        $occupancy = $this->occupancyService->getSlotOccupancy($this->labSpace, $slotStart, $slotEnd);

        $this->assertEquals(0, $occupancy['current'], 'Pending bookings should not count toward occupancy');
    }

    #[Test]
    public function it_ignores_cancelled_bookings()
    {
        $slotStart = Carbon::now()->setHour(10)->setMinute(0)->setSecond(0);
        $slotEnd = $slotStart->copy()->addHour();

        // Create a cancelled booking (should not count)
        LabBooking::factory()->create([
            'lab_space_id' => $this->labSpace->id,
            'starts_at' => $slotStart,
            'ends_at' => $slotEnd,
            'status' => LabBooking::STATUS_CANCELLED,
        ]);

        $occupancy = $this->occupancyService->getSlotOccupancy($this->labSpace, $slotStart, $slotEnd);

        $this->assertEquals(0, $occupancy['current'], 'Cancelled bookings should not count');
    }

    #[Test]
    public function it_detects_full_slots()
    {
        $slotStart = Carbon::now()->setHour(10)->setMinute(0)->setSecond(0);
        $slotEnd = $slotStart->copy()->addHour();

        // Fill both slots
        LabBooking::factory()->count(2)->create([
            'lab_space_id' => $this->labSpace->id,
            'starts_at' => $slotStart,
            'ends_at' => $slotEnd,
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        $occupancy = $this->occupancyService->getSlotOccupancy($this->labSpace, $slotStart, $slotEnd);

        $this->assertEquals(2, $occupancy['current']);
        $this->assertEquals(2, $occupancy['capacity']);
        $this->assertEquals(0, $occupancy['available']);
        $this->assertEquals(100, $occupancy['percentage']);
        $this->assertTrue($occupancy['is_full']);
        $this->assertTrue($occupancy['is_near_full']);
    }

    #[Test]
    public function it_detects_near_full_slots_at_80_percent()
    {
        $slotStart = Carbon::now()->setHour(10)->setMinute(0)->setSecond(0);
        $slotEnd = $slotStart->copy()->addHour();

        // Create a lab with 5 slots per hour
        $space = LabSpace::factory()->create(['slots_per_hour' => 5]);

        // Fill 4 out of 5 slots (80%)
        LabBooking::factory()->count(4)->create([
            'lab_space_id' => $space->id,
            'starts_at' => $slotStart,
            'ends_at' => $slotEnd,
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        $occupancy = $this->occupancyService->getSlotOccupancy($space, $slotStart, $slotEnd);

        $this->assertEquals(80, $occupancy['percentage']);
        $this->assertTrue($occupancy['is_near_full']);
        $this->assertFalse($occupancy['is_full']);
    }

    #[Test]
    public function it_handles_overlapping_bookings()
    {
        $slotStart = Carbon::now()->setHour(10)->setMinute(0)->setSecond(0);
        $slotEnd = $slotStart->copy()->addHour();

        // Create a booking that starts before the slot and ends during it
        LabBooking::factory()->create([
            'lab_space_id' => $this->labSpace->id,
            'starts_at' => $slotStart->copy()->subMinutes(30),
            'ends_at' => $slotStart->copy()->addMinutes(30),
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        // Create a booking that starts during the slot and ends after
        LabBooking::factory()->create([
            'lab_space_id' => $this->labSpace->id,
            'starts_at' => $slotStart->copy()->addMinutes(30),
            'ends_at' => $slotEnd->copy()->addMinutes(30),
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        $occupancy = $this->occupancyService->getSlotOccupancy($this->labSpace, $slotStart, $slotEnd);

        // Both overlapping bookings should count
        $this->assertEquals(2, $occupancy['current']);
        $this->assertTrue($occupancy['is_full']);
    }

    #[Test]
    public function it_uses_slots_per_hour_for_capacity()
    {
        $slotStart = Carbon::now()->setHour(10)->setMinute(0)->setSecond(0);
        $slotEnd = $slotStart->copy()->addHour();

        // Create a space with slots_per_hour but low capacity
        $space = LabSpace::factory()->create([
            'slots_per_hour' => 3,
            'capacity' => 100,  // Shouldn't be used
        ]);

        $occupancy = $this->occupancyService->getSlotOccupancy($space, $slotStart, $slotEnd);

        // Should use slots_per_hour (3), not capacity (100)
        $this->assertEquals(3, $occupancy['capacity']);
    }

    #[Test]
    public function it_falls_back_to_capacity_if_slots_per_hour_missing()
    {
        $slotStart = Carbon::now()->setHour(10)->setMinute(0)->setSecond(0);
        $slotEnd = $slotStart->copy()->addHour();

        // Create a space with slots_per_hour = 0 (treated as not set)
        $space = LabSpace::factory()->create([
            'slots_per_hour' => 0,
            'capacity' => 5,
        ]);

        $occupancy = $this->occupancyService->getSlotOccupancy($space, $slotStart, $slotEnd);

        // Should fall back to capacity (0 is treated as not set)
        $this->assertEquals(5, $occupancy['capacity']);
    }

    #[Test]
    public function it_defaults_to_one_slot_if_both_null()
    {
        $slotStart = Carbon::now()->setHour(10)->setMinute(0)->setSecond(0);
        $slotEnd = $slotStart->copy()->addHour();

        // Create a space with both capacity fields set to 0 (treated as not set)
        $space = LabSpace::factory()->create([
            'slots_per_hour' => 0,
            'capacity' => 0,
        ]);

        $occupancy = $this->occupancyService->getSlotOccupancy($space, $slotStart, $slotEnd);

        // Should default to 1 (0 values are treated as not set)
        $this->assertEquals(1, $occupancy['capacity']);
    }

    #[Test]
    public function canBook_returns_true_when_space_available()
    {
        $slotStart = Carbon::now()->setHour(10)->setMinute(0)->setSecond(0);
        $slotEnd = $slotStart->copy()->addHour();

        $canBook = $this->occupancyService->canBook($this->labSpace, $slotStart, $slotEnd);

        $this->assertTrue($canBook);
    }

    #[Test]
    public function canBook_returns_false_when_slot_full()
    {
        $slotStart = Carbon::now()->setHour(10)->setMinute(0)->setSecond(0);
        $slotEnd = $slotStart->copy()->addHour();

        // Fill all slots
        LabBooking::factory()->count(2)->create([
            'lab_space_id' => $this->labSpace->id,
            'starts_at' => $slotStart,
            'ends_at' => $slotEnd,
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        $canBook = $this->occupancyService->canBook($this->labSpace, $slotStart, $slotEnd);

        $this->assertFalse($canBook);
    }

    #[Test]
    public function getDateRangeOccupancy_returns_complete_occupancy_map()
    {
        $start = Carbon::now()->setHour(0)->setMinute(0)->setSecond(0);
        $end = $start->copy()->addDays(2);

        $occupancies = $this->occupancyService->getDateRangeOccupancy($this->labSpace, $start, $end);

        // Should have 3 days
        $this->assertCount(3, $occupancies);

        // Each day should have hourly data
        foreach ($occupancies as $dateKey => $hours) {
            // Should have hours from 9 AM to 5 PM (9, 10, 11, 12, 13, 14, 15, 16)
            $this->assertCount(8, $hours);

            // Each hour should have occupancy data
            foreach ($hours as $hour => $occupancy) {
                $this->assertIsInt($hour);
                $this->assertIsArray($occupancy);
                $this->assertArrayHasKey('current', $occupancy);
                $this->assertArrayHasKey('capacity', $occupancy);
                $this->assertArrayHasKey('percentage', $occupancy);
            }
        }
    }

    #[Test]
    public function getDateRangeOccupancy_respects_operating_hours()
    {
        $start = Carbon::now()->setHour(0)->setMinute(0)->setSecond(0);
        $end = $start->copy()->addDay();

        // Create space with specific hours
        $space = LabSpace::factory()->create([
            'opens_at' => '10:00',
            'closes_at' => '14:00',
            'slots_per_hour' => 1,
        ]);

        $occupancies = $this->occupancyService->getDateRangeOccupancy($space, $start, $end);

        // Should only have hours 10, 11, 12, 13 (10 AM to 2 PM)
        $firstDay = reset($occupancies);
        $this->assertCount(4, $firstDay);
        $this->assertArrayHasKey(10, $firstDay);
        $this->assertArrayHasKey(13, $firstDay);
        $this->assertArrayNotHasKey(9, $firstDay);
        $this->assertArrayNotHasKey(14, $firstDay);
    }

    #[Test]
    public function getDateRangeOccupancy_defaults_to_8am_8pm_if_hours_not_set()
    {
        $start = Carbon::now()->setHour(0)->setMinute(0)->setSecond(0);
        $end = $start->copy()->addDay();

        // Create space without explicit hours
        $space = LabSpace::factory()->create([
            'opens_at' => null,
            'closes_at' => null,
            'slots_per_hour' => 1,
        ]);

        $occupancies = $this->occupancyService->getDateRangeOccupancy($space, $start, $end);

        // Should default to 8 AM - 8 PM (8-19)
        $firstDay = reset($occupancies);
        $this->assertCount(12, $firstDay);
        $this->assertArrayHasKey(8, $firstDay);
        $this->assertArrayHasKey(19, $firstDay);
        $this->assertArrayNotHasKey(7, $firstDay);
        $this->assertArrayNotHasKey(20, $firstDay);
    }
}
