<?php

namespace Tests\Feature;

use App\Models\LabSpace;
use App\Models\Plan;
use App\Models\User;
use App\Models\PlanSubscription;
use App\Services\LabBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabBookingLogicTest extends TestCase
{
    use RefreshDatabase;

    protected LabBookingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LabBookingService::class);
    }

    public function test_skip_and_append_strategy()
    {
        $space = LabSpace::factory()->create([
            'capacity' => 1,
            'available_from' => '09:00',
            'available_until' => '17:00'
        ]);

        $user = User::factory()->create();
        
        $plan = Plan::factory()->create(['invoice_interval' => 'month']);
        $plan->systemFeatures()->attach(
            \App\Models\SystemFeature::firstOrCreate(['slug' => 'lab-hours-monthly'], ['name' => 'Lab Hours', 'value_type' => 'number', 'default_value' => '0']),
            ['value' => '100']
        );
        $user->update(['plan_id' => $plan->id]);
        $user = $user->fresh();

        $day1 = Carbon::parse('2026-03-09'); // Monday
        $day2 = Carbon::parse('2026-03-11'); // Wednesday
        
        // Fill Wednesday
        \App\Models\LabBooking::create([
            'lab_space_id' => $space->id,
            'user_id' => User::factory()->create()->id,
            'starts_at' => $day2->copy()->setTime(9, 0, 0),
            'ends_at' => $day2->copy()->setTime(10, 0, 0),
            'status' => 'confirmed',
            'purpose' => 'Blocking Wednesday'
        ]);

        $data = [
            'lab_space_id' => $space->id,
            'slots' => [
                ['starts_at' => $day1->copy()->setTime(9, 0, 0)->toDateTimeString(), 'ends_at' => $day1->copy()->setTime(10, 0, 0)->toDateTimeString()]
            ],
            'type' => 'recurring',
            'metadata' => [
                'days_of_week' => ['Mon', 'Wed'],
                'target_count' => 2
            ],
            'purpose' => 'Recurring Test'
        ];

        $result = $this->service->initiateBooking($user, $data);

        $this->assertTrue($result['success']);
        $holds = \App\Models\SlotHold::where('reference', $result['reference'])->get();
        
        $this->assertCount(2, $holds);
        $this->assertEquals($day1->toDateString(), $holds[0]->starts_at->toDateString());
        // Should have skipped $day2 (Wed) and picked Monday (2026-03-16)
        $this->assertEquals('2026-03-16', $holds[1]->starts_at->toDateString());
    }

    public function test_yearly_subscription_quota_splitting()
    {
        $space = LabSpace::factory()->create(['hourly_rate' => 10]);
        $user = User::factory()->create();
        
        $plan = Plan::factory()->create([
            'invoice_interval' => 'year'
        ]);
        $plan->systemFeatures()->attach(
            \App\Models\SystemFeature::firstOrCreate(['slug' => 'lab-hours-monthly'], ['name' => 'Lab Hours', 'value_type' => 'number', 'default_value' => '0']),
            ['value' => '10']
        );
        $user->update(['plan_id' => $plan->id]);
        $user = $user->fresh();

        // Mock dates that are NOT the current month to avoid current month logic
        $thisMonth = Carbon::parse('2026-06-10'); // Within term
        $nextMonth = Carbon::parse('2026-07-10'); // Outside term
        $termEnd = Carbon::parse('2026-06-20');
        
        PlanSubscription::create([
            'subscriber_id' => $user->id,
            'subscriber_type' => 'user',
            'plan_id' => $plan->id,
            'name' => 'Yearly Sub',
            'starts_at' => Carbon::parse('2025-06-21'),
            'ends_at' => $termEnd,
            'slug' => 'yearly-sub-' . uniqid(),
            'status' => 'active',
        ]);

        $slots = [
            ['starts_at' => $thisMonth->copy()->setTime(9,0), 'ends_at' => $thisMonth->copy()->setTime(10,0)],
            ['starts_at' => $nextMonth->copy()->setTime(9,0), 'ends_at' => $nextMonth->copy()->setTime(10,0)]
        ];

        $result = $this->service->calculateBookingPriceWithCommitments($user, $space, $slots);

        $this->assertEquals(10, (float)$result['total_price']); 
        $this->assertCount(2, $result['breakdown']);
        
        $this->assertEquals('commitment', $result['breakdown'][0]['type']);
        $this->assertEquals('post_term', $result['breakdown'][1]['type']);
    }
}
