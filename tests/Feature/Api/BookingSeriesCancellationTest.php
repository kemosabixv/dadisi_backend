<?php

namespace Tests\Feature\Api;

use App\Models\BookingSeries;
use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingSeriesCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(); // Seed roles/permissions if needed
    }

    public function test_user_can_cancel_their_own_booking_series()
    {
        $user = User::factory()->create();
        $space = LabSpace::factory()->create();
        
        $series = BookingSeries::create([
            'user_id' => $user->id,
            'lab_space_id' => $space->id,
            'status' => 'confirmed',
            'reference' => 'SERIES-' . uniqid(),
            'total_hours' => 3.0,
        ]);

        // Create 3 future bookings
        for ($i = 1; $i <= 3; $i++) {
            LabBooking::factory()->create([
                'booking_series_id' => $series->id,
                'user_id' => $user->id,
                'lab_space_id' => $space->id,
                'starts_at' => now()->addDays($i),
                'ends_at' => now()->addDays($i)->addHour(),
                'status' => LabBooking::STATUS_CONFIRMED,
                'quota_consumed' => 1.0,
            ]);
        }

        $response = $this->actingAs($user)
            ->postJson("/api/bookings/series/{$series->id}/cancel", [
                'cancellation_reason' => 'Changed my mind'
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Booking series cancelled successfully');

        $this->assertEquals(BookingSeries::STATUS_CANCELLED, $series->fresh()->status);
        $this->assertEquals(0, LabBooking::where('booking_series_id', $series->id)->where('status', '!=', LabBooking::STATUS_CANCELLED)->count());
    }

    public function test_cancel_series_requires_authentication()
    {
        $space = LabSpace::factory()->create();
        $series = BookingSeries::create([
            'user_id' => User::factory()->create()->id,
            'lab_space_id' => $space->id,
            'reference' => 'SERIES-' . uniqid(),
            'total_hours' => 1.0,
            'status' => 'confirmed',
        ]);

        $this->postJson("/api/bookings/series/{$series->id}/cancel", [
            'cancellation_reason' => 'Unauthorized'
        ])->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_cancel_others_series()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $space = LabSpace::factory()->create();
        
        $series = BookingSeries::create([
            'user_id' => $owner->id,
            'lab_space_id' => $space->id,
            'reference' => 'SERIES-' . uniqid(),
            'total_hours' => 1.0,
            'status' => 'confirmed',
        ]);

        $this->actingAs($otherUser)
            ->postJson("/api/bookings/series/{$series->id}/cancel", [
                'cancellation_reason' => 'Malicious'
            ])->assertStatus(403);
    }

    public function test_cancel_non_existent_series_returns_404()
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->postJson("/api/bookings/series/99999/cancel", [
                'cancellation_reason' => 'None'
            ])->assertStatus(404);
    }

    public function test_cancelling_series_restores_quota()
    {
        $user = User::factory()->create();
        $plan = \App\Models\Plan::factory()->create(['is_active' => true]);
        $feature = \App\Models\SystemFeature::updateOrCreate(
            ['slug' => 'lab_hours_monthly'],
            [
                'name' => 'lab_hours_monthly',
                'value_type' => 'number',
                'default_value' => '0',
                'is_active' => true,
            ]
        );
        $plan->systemFeatures()->attach($feature->id, ['value' => '10']);

        \Illuminate\Support\Facades\DB::table('subscriptions')->insert([
            'subscriber_id' => $user->id,
            'subscriber_type' => get_class($user),
            'plan_id' => $plan->id,
            'slug' => 'lab-subscription-' . $user->id,
            'name' => 'Lab Subscription',
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subscriptionId = \Illuminate\Support\Facades\DB::getPdo()->lastInsertId();
        
        $user->update([
            'plan_id' => $plan->id, 
            'plan_subscription_id' => $subscriptionId,
            'subscription_status' => 'active',
        ]);

        $space = LabSpace::factory()->create();
        
        // Create quota commitment manually for this month
        $thisMonth = now()->startOfMonth();
        $commitment = \App\Models\QuotaCommitment::create([
            'user_id' => $user->id,
            'month_date' => $thisMonth,
            'committed_hours' => 10,
            'used_hours' => 0,
            'warning_threshold_percent' => 80,
            'warned_at_threshold' => false,
        ]);

        $quotaService = app(\App\Services\QuotaService::class);
        $initialStatus = $quotaService->getQuotaStatus($user, $thisMonth);
        $initialUsed = $initialStatus['used_hours'];

        $series = BookingSeries::create([
            'user_id' => $user->id,
            'lab_space_id' => $space->id,
            'reference' => 'SERIES-' . uniqid(),
            'total_hours' => 2.0,
            'status' => 'confirmed',
        ]);

        // 2 slots, each 1 hour
        LabBooking::factory()->count(2)->create([
            'booking_series_id' => $series->id,
            'user_id' => $user->id,
            'lab_space_id' => $space->id,
            'starts_at' => now()->addDays(1)->setMinute(0)->setSecond(0),
            'ends_at' => now()->addDays(1)->addHour()->setMinute(0)->setSecond(0),
            'quota_consumed' => 1.0,
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);

        // Simulate quota consumption
        $commitment = \App\Models\QuotaCommitment::where('user_id', $user->id)->first();
        $this->assertNotNull($commitment, "Quota commitment should exist");
        $commitment->update(['used_hours' => 2.0]);

        $this->actingAs($user)
            ->postJson("/api/bookings/series/{$series->id}/cancel");

        $finalStatus = $quotaService->getQuotaStatus($user);
        $finalUsed = $finalStatus['used_hours'];
        
        $this->assertEquals($initialUsed, $finalUsed, "Quota should be restored to initial state after cancellation");
    }

    public function test_cancelling_paid_series_creates_refund_request()
    {
        $user = User::factory()->create();
        $space = LabSpace::factory()->create();
        
        $series = BookingSeries::create([
            'user_id' => $user->id,
            'lab_space_id' => $space->id,
            'reference' => 'SERIES-' . uniqid(),
            'total_hours' => 2.0,
            'status' => 'confirmed',
        ]);

        $booking = LabBooking::factory()->create([
            'booking_series_id' => $series->id,
            'user_id' => $user->id,
            'lab_space_id' => $space->id,
            'starts_at' => now()->addDays(1),
            'status' => LabBooking::STATUS_CONFIRMED,
            'payment_method' => 'mpesa',
        ]);

        $payment = Payment::create([
            'payable_type' => 'lab_booking',
            'payable_id' => $booking->id,
            'amount' => 1000,
            'status' => 'paid',
            'method' => 'mpesa',
            'external_id' => 'TEST_PESAPAL_ID',
            'order_reference' => 'ORD-' . uniqid(),
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/bookings/series/{$series->id}/cancel");

        $response->assertJsonPath('refund_initiated', true);
        
        $this->assertDatabaseHas('refunds', [
            'refundable_id' => $booking->id,
            'status' => 'pending',
            'original_amount' => 1000.00
        ]);
    }
}
