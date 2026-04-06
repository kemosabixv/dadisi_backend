<?php

namespace Tests\Feature;

use App\Models\LabSpace;
use App\Models\User;
use App\Models\LabBooking;
use App\Services\LabBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LabTimezoneLogicTest extends TestCase
{
    use RefreshDatabase;

    protected LabBookingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Notification::fake();
        
        // Mock ExchangeRateService to prevent Guzzle network calls
        $mockExchange = $this->createMock(\App\Services\Contracts\ExchangeRateServiceContract::class);
        $this->app->instance(\App\Services\Contracts\ExchangeRateServiceContract::class, $mockExchange);

        $this->service = app(LabBookingService::class);
        
        // Seed basic roles for logic tests
        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'lab_supervisor', 'guard_name' => 'web']);
        
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'access_admin_panel', 'guard_name' => 'web']);
        $adminRole->givePermissionTo($permission);

        $editPermission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'edit_lab_space', 'guard_name' => 'web']);
        $adminRole->givePermissionTo($editPermission);

        $managePermission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage_lab_spaces', 'guard_name' => 'web']);
        $adminRole->givePermissionTo($managePermission);
    }

    /**
     * Test normalization of "naive" user inputs (interpretAsLabTime).
     */
    public function test_normalization_to_lab_local_context()
    {
        // Lab in Nairobi (+3)
        $lab = LabSpace::factory()->create(['timezone' => 'Africa/Nairobi']);

        // User says "2026-04-04 09:00:00"
        $input = "2026-04-04 09:00:00";
        
        $normalized = $this->service->interpretAsLabTime($input, $lab);

        // 9:00 AM Nairobi should be 6:00 AM UTC
        $this->assertEquals('2026-04-04 06:00:00', $normalized->toDateTimeString());
        $this->assertEquals('UTC', $normalized->timezoneName);
    }

    /**
     * Test availability checks respect lab-local operating hours.
     */
    public function test_check_availability_respects_lab_local_hours()
    {
        // Lab in USA/Pacific (-8 or -7 depending on DST)
        // Let's use a fixed offset one to be sure: America/Los_Angeles
        $lab = LabSpace::factory()->create([
            'timezone' => 'America/Los_Angeles',
            'available_from' => '09:00',
            'available_until' => '17:00',
            'capacity' => 1,
            'operating_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
        ]);

        // 10:00 AM LA time on 2026-04-04 (PDT, -7)
        // 10:00 AM PDT = 17:00 UTC
        $startTime = Carbon::parse('2026-04-04 17:00:00', 'UTC');
        $endTime = Carbon::parse('2026-04-04 18:00:00', 'UTC');

        $available = $this->service->checkAvailability($lab, $startTime, $endTime);
        $this->assertTrue($available, "Should be available at 10 AM local (17:00 UTC)");

        // 8:00 AM LA time = 15:00 UTC (Before opening)
        $earlyStart = Carbon::parse('2026-04-04 15:00:00', 'UTC');
        $earlyEnd = Carbon::parse('2026-04-04 16:00:00', 'UTC');
        
        $availableEarly = $this->service->checkAvailability($lab, $earlyStart, $earlyEnd);
        $this->assertFalse($availableEarly, "Should be unavailable at 8 AM local (Before 9 AM opening)");
    }

    /**
     * Test quota boundaries are calculated relative to lab timezone.
     */
    public function test_quota_boundaries_use_lab_timezone()
    {
        $user = User::factory()->create();
        $lab = LabSpace::factory()->create(['timezone' => 'Africa/Nairobi']); // +3

        // Create a booking exactly at 1 AM Nairobi, April 1st.
        // 01:00 EAT April 1 = 22:00 UTC March 31.
        $marginalTime = Carbon::parse('2026-03-31 22:00:00', 'UTC');
        
        LabBooking::factory()->create([
            'user_id' => $user->id,
            'lab_space_id' => $lab->id,
            'starts_at' => $marginalTime,
            'ends_at' => $marginalTime->copy()->addHour(),
            'quota_consumed' => true,
            'status' => 'confirmed'
        ]);

        // Mock "now" to be mid-April in Nairobi
        Carbon::setTestNow(Carbon::parse('2026-04-15 12:00:00', 'Africa/Nairobi'));

        // If boundaries were UTC, this 10 PM March 31st booking would be in MARCH.
        // But in Nairobi, it's 1 AM April 1st, so it belongs to APRIL quota.
        $usedHours = $this->service->getUsedHoursThisMonth($user, $lab);
        
        $this->assertEquals(1, $usedHours, "Booking at 1 AM Nairobi Local should count towards current month (April).");
        
        Carbon::setTestNow(); // Reset
    }

    /**
     * Test attendance consolidation across UTC midnight (The "Check-in Regression").
     */
    public function test_checkin_consolidation_across_utc_midnight()
    {
        $user = User::factory()->create();
        $lab = LabSpace::factory()->create(['timezone' => 'Africa/Nairobi']); // +3

        // Booking 1: 10 PM - 11 PM Nairobi (19:00 - 20:00 UTC)
        $b1 = LabBooking::factory()->create([
            'user_id' => $user->id,
            'lab_space_id' => $lab->id,
            'starts_at' => Carbon::parse('2026-04-04 19:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-04-04 20:00:00', 'UTC'),
            'status' => 'confirmed'
        ]);

        // Booking 2: 1 AM - 2 AM Nairobi (Next day local, but same day UTC: 22:00 - 23:00 UTC)
        // Wait, 1 AM Nairobi is 10 PM UTC PREVIOUS day. 
        // Let's use 11 PM Nairobi (20:00 UTC) and 1 AM Nairobi (22:00 UTC).
        // In UTC, both are April 4th.
        // In Nairobi, B1 is April 4th, B2 is April 5th.
        
        $b2 = LabBooking::factory()->create([
            'user_id' => $user->id,
            'lab_space_id' => $lab->id,
            'starts_at' => Carbon::parse('2026-04-04 22:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-04-04 23:00:00', 'UTC'),
            'status' => 'confirmed'
        ]);

        // Check in to B1
        $this->service->checkIn($b1);

        // Verify ONLY B1 is checked in (Consolidation should NOT pick up B2 because it's locally 'tomorrow')
        $this->assertNotNull($b1->fresh()->checked_in_at);
        $this->assertNull($b2->fresh()->checked_in_at, "B2 should not be consolidated as it is locally the next day.");

        // Now Check in to B2
        $this->service->checkIn($b2);
        $this->assertNotNull($b2->fresh()->checked_in_at);

        // Test Undo for B1
        $this->service->undoCheckIn($b1);
        $this->assertNull($b1->fresh()->checked_in_at);
        $this->assertNotNull($b2->fresh()->checked_in_at, "B2 should remains checked in during B1 undo.");
    }

    /**
     * Test Admin CRUD logic for timezone immutability.
     */
    public function test_timezone_is_immutable_after_creation()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $lab = LabSpace::factory()->create(['timezone' => 'Africa/Nairobi']);

        $response = $this->actingAs($admin)
            ->putJson("/api/admin/spaces/{$lab->id}", [
                'name' => 'Renamed Lab',
                'timezone' => 'Europe/London' // Attempt to change
            ]);

        if ($response->status() !== 200) {
            dump($response->json());
        }
        $response->assertStatus(200);
        
        $lab->refresh();
        $this->assertEquals('Renamed Lab', $lab->name);
        $this->assertEquals('Africa/Nairobi', $lab->timezone, "Timezone should remain Africa/Nairobi despite update attempt.");
    }
}
