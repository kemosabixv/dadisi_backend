<?php

namespace Tests\Feature;

use App\Models\LabBooking;
use App\Models\LabMaintenanceBlock;
use App\Models\LabSpace;
use App\Models\User;
use App\Services\Contracts\LabBookingServiceContract;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LabAvailabilityNPlusOneTest extends TestCase
{
    use RefreshDatabase;

    protected bool $shouldSeedRoles = true;
    protected LabBookingServiceContract $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LabBookingServiceContract::class);
    }

    #[Test]
    public function availability_calendar_executes_constant_number_of_queries()
    {
        // 1. Setup a Lab Space
        $lab = LabSpace::factory()->create([
            'opens_at' => '08:00',
            'closes_at' => '20:00', // 12 hours
            'capacity' => 5,
            'is_available' => true,
        ]);

        // 2. Create some existing bookings and maintenance to make the test realistic
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        // Create 10 random bookings in the range
        LabBooking::factory()->count(10)->create([
            'lab_space_id' => $lab->id,
            'starts_at' => $start->copy()->addDays(rand(1, 20))->setHour(10),
            'ends_at' => $start->copy()->addDays(rand(1, 20))->setHour(12),
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        // Create a maintenance block
        LabMaintenanceBlock::factory()->create([
            'lab_space_id' => $lab->id,
            'starts_at' => $start->copy()->addDays(15)->setHour(8),
            'ends_at' => $start->copy()->addDays(15)->setHour(20),
        ]);

        // 3. Enable Query Log
        DB::enableQueryLog();
        DB::flushQueryLog();

        // 4. Action: Get availability for a Full Month (approx 360 hourly slots)
        $result = $this->service->getAvailabilityCalendar($lab, $start, $end);

        $queries = DB::getQueryLog();
        
        // 5. Analysis
        $bookingQueries = collect($queries)->filter(function ($query) {
            $sql = strtolower($query['query']);
            return str_contains($sql, 'from `lab_bookings`') || str_contains($sql, '"lab_bookings"');
        });

        $maintenanceQueries = collect($queries)->filter(function ($query) {
            $sql = strtolower($query['query']);
            return str_contains($sql, 'from `lab_maintenance_blocks`') || str_contains($sql, '"lab_maintenance_blocks"');
        });

        $holdQueries = collect($queries)->filter(function ($query) {
            $sql = strtolower($query['query']);
            return str_contains($sql, 'from `slot_holds`') || str_contains($sql, '"slot_holds"');
        });

        // BEFORE THE FIX: bookingQueries/holdQueries would be ~360 (one per slot)
        // AFTER THE FIX: they should be exactly 1 (for the range)
        $this->assertCount(1, $bookingQueries, "N+1 Issue: Expected exactly 1 query to lab_bookings, found " . $bookingQueries->count());
        $this->assertCount(1, $maintenanceQueries, "N+1 Issue: Expected exactly 1 query to lab_maintenance_blocks, found " . $maintenanceQueries->count());
        $this->assertCount(1, $holdQueries, "N+1 Issue: Expected exactly 1 query to slot_holds, found " . $holdQueries->count());

        // 6. Verify data integrity
        $this->assertArrayHasKey('events', $result);
        $this->assertNotEmpty($result['events']);
        
        // Verify a specific date (the maintenance day)
        $maintenanceDate = $start->copy()->addDays(15)->format('Y-m-d');
        $this->assertArrayHasKey($maintenanceDate, $result['events']);
        
        // Check that at least one slot on the maintenance day is marked as full/blocked
        $maintenanceSlots = collect($result['events'][$maintenanceDate]['hours']);
        $blockedSlots = $maintenanceSlots->filter(fn($s) => $s['status'] === 'full');
        $this->assertNotEmpty($blockedSlots, "Data Integrity: Maintenance day should have blocked slots.");
    }
}
