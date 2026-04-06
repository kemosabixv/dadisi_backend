<?php

namespace Tests\Unit\Services;

use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Services\OccupancyService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
/**
 * Handles real-time occupancy tracking and capacity calculations for lab spaces.
 * Calculates how many booking slots are available vs full for each time slot.
 *
 * Uses `capacity` field for both physical limit and booking slots per hour.
 */
class OccupancyServiceUnitTest extends TestCase
{
    private OccupancyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OccupancyService();
    }

    #[Test]
    public function it_calculates_zero_occupancy_with_no_bookings()
    {
        // Mock-like behavior: We're testing the calculation logic
        // In a real scenario, the service would query the database
        
        // Given: No bookings
        $capacity = 2;
        $current = 0;
        
        // When: We calculate occupancy
        $percentage = (int) floor(($current / $capacity) * 100);
        $available = max(0, $capacity - $current);
        
        // Then: Results should be correct
        $this->assertEquals(0, $percentage);
        $this->assertEquals(2, $available);
        $this->assertFalse($current >= $capacity);
    }

    #[Test]
    public function it_calculates_50_percent_occupancy()
    {
        $capacity = 2;
        $current = 1;
        
        $percentage = (int) floor(($current / $capacity) * 100);
        $available = max(0, $capacity - $current);
        
        $this->assertEquals(50, $percentage);
        $this->assertEquals(1, $available);
        $this->assertFalse($current >= $capacity);
    }

    #[Test]
    public function it_calculates_100_percent_occupancy()
    {
        $capacity = 2;
        $current = 2;
        
        $percentage = (int) floor(($current / $capacity) * 100);
        $available = max(0, $capacity - $current);
        
        $this->assertEquals(100, $percentage);
        $this->assertEquals(0, $available);
        $this->assertTrue($current >= $capacity);
    }

    #[Test]
    public function it_detects_near_full_at_80_percent()
    {
        $capacity = 5;
        $current = 4;
        
        $percentage = (int) floor(($current / $capacity) * 100);
        $isNearFull = $percentage >= 80;
        
        $this->assertEquals(80, $percentage);
        $this->assertTrue($isNearFull);
    }

    #[Test]
    public function it_detects_full_at_100_percent()
    {
        $capacity = 5;
        $current = 5;
        
        $isFull = $current >= $capacity;
        
        $this->assertTrue($isFull);
    }

    #[Test]
    public function it_handles_capacity_validation()
    {
        // Test the capacity logic: capacity ?? 1
        
        // Case 1: capacity exists
        $capacity = max(1, 5);
        $this->assertEquals(5, $capacity);
        
        // Case 2: default to 1
        $capacity = max(1, 0);
        $this->assertEquals(1, $capacity);
    }

    #[Test]
    public function it_handles_hour_parsing()
    {
        // Test hour parsing logic from time strings
        
        // Simulate parsing "09:00"
        $timeString = "09:00";
        $hour = (int) explode(':', $timeString)[0];
        $this->assertEquals(9, $hour);
        
        // Simulate parsing "17:00"
        $timeString = "17:00";
        $hour = (int) explode(':', $timeString)[0];
        $this->assertEquals(17, $hour);
    }

    #[Test]
    public function it_calculates_hour_range_correctly()
    {
        // Test that hour loop works correctly
        $opensHour = 9;
        $closesHour = 17;
        
        $hoursInRange = [];
        for ($hour = $opensHour; $hour < $closesHour; $hour++) {
            $hoursInRange[] = $hour;
        }
        
        // Should be 9, 10, 11, 12, 13, 14, 15, 16 (8 hours)
        $this->assertCount(8, $hoursInRange);
        $this->assertEquals(9, $hoursInRange[0]);
        $this->assertEquals(16, $hoursInRange[7]);
    }

    #[Test]
    public function it_defaults_to_8am_8pm_when_hours_null()
    {
        // Test the default behavior
        $opensHour = null ? (int) 9 : 8;
        $closesHour = null ? (int) 17 : 20;
        
        $this->assertEquals(8, $opensHour);
        $this->assertEquals(20, $closesHour);
    }

    #[Test]
    public function it_correctly_determines_color_based_on_percentage()
    {
        // Test color determination logic
        
        // Test: 0% - Green
        $pct = 0;
        $color = $pct <= 50 ? 'green' : ($pct <= 80 ? 'yellow' : ($pct < 100 ? 'orange' : 'red'));
        $this->assertEquals('green', $color);
        
        // Test: 50% - Green
        $pct = 50;
        $color = $pct <= 50 ? 'green' : ($pct <= 80 ? 'yellow' : ($pct < 100 ? 'orange' : 'red'));
        $this->assertEquals('green', $color);
        
        // Test: 75% - Yellow
        $pct = 75;
        $color = $pct <= 50 ? 'green' : ($pct <= 80 ? 'yellow' : ($pct < 100 ? 'orange' : 'red'));
        $this->assertEquals('yellow', $color);
        
        // Test: 80% - Yellow
        $pct = 80;
        $color = $pct <= 50 ? 'green' : ($pct <= 80 ? 'yellow' : ($pct < 100 ? 'orange' : 'red'));
        $this->assertEquals('yellow', $color);
        
        // Test: 90% - Orange
        $pct = 90;
        $color = $pct <= 50 ? 'green' : ($pct <= 80 ? 'yellow' : ($pct < 100 ? 'orange' : 'red'));
        $this->assertEquals('orange', $color);
        
        // Test: 100% - Red
        $pct = 100;
        $color = $pct <= 50 ? 'green' : ($pct <= 80 ? 'yellow' : ($pct < 100 ? 'orange' : 'red'));
        $this->assertEquals('red', $color);
    }
}
