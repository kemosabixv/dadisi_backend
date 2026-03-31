<?php

namespace Tests\Feature;

use App\Models\LabSpace;
use App\Models\User;
use App\Services\LabBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LabBookingPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $shouldSeedRoles = true;
    protected LabBookingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LabBookingService::class);
    }

    #[Test]
    public function recurring_discovery_uses_efficient_query_count()
    {
        // Setup
        $user = User::factory()->create();
        $lab = LabSpace::factory()->create([
            'opens_at' => '08:00',
            'closes_at' => '20:00',
            'operating_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
        ]);

        $params = [
            'lab_space_id' => $lab->id,
            'target_count' => 10,
            'days_of_week' => ['Mon', 'Wed', 'Fri'],
            'start_time' => '10:00',
            'duration_minutes' => 120,
            'start_date' => now()->format('Y-m-d'),
        ];

        // Enable query log
        DB::enableQueryLog();
        DB::flushQueryLog();

        // Action
        $this->service->discoverRecurringSlots($lab->id, $params, $user);

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // Assertions
        // Without pre-fetching, it would be at least O(target_count) queries for bookings/closures
        // With pre-fetching, it should be O(1) database trips for those entities
        $this->assertLessThan(20, $queryCount, "Recurring discovery query count ($queryCount) is too high. Pre-fetching may not be working.");
    }

    #[Test]
    public function flexible_discovery_uses_efficient_query_count()
    {
        // Setup
        $user = User::factory()->create();
        $lab = LabSpace::factory()->create([
            'opens_at' => '08:00',
            'closes_at' => '20:00',
            'operating_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
        ]);

        $params = [
            'lab_space_id' => $lab->id,
            'target_hours' => 20,
            'max_daily_hours' => 4,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(14)->format('Y-m-d'),
            'preferred_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
        ];

        // Enable query log
        DB::enableQueryLog();
        DB::flushQueryLog();

        // Action
        $this->service->discoverFlexibleSlots($lab->id, $params, $user);

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // Assertions
        // Without pre-fetching, O(days) queries.
        $this->assertLessThan(25, $queryCount, "Flexible discovery query count ($queryCount) is too high. Pre-fetching may not be working.");
    }
}
