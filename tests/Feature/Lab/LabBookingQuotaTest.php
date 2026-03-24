<?php

namespace Tests\Feature\Lab;

use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\QuotaCommitment;
use App\Models\User;
use App\Services\LabBookingService;
use App\Services\QuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LabBookingService Quota Integration Tests - PHASE 2
 *
 * Tests quota validation and consumption in the booking workflow
 *
 * Test Groups:
 * 1. Quota Validation (single-month bookings)
 * 2. Recurring Quota Validation (multi-month bookings)
 * 3. Quota Consumption (deducting during confirmation)
 * 4. Quota Release (cancellation reversals)
 * 5. Edge Cases (exhausted quota, over-quota, etc.)
 */
class LabBookingQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected LabSpace $lab;

    protected Plan $plan;

    protected LabBookingService $bookingService;

    protected QuotaService $quotaService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bookingService = app(LabBookingService::class);
        $this->quotaService = app(QuotaService::class);

        // Create lab space
        $this->lab = LabSpace::factory()->create([
            'name' => 'Test Lab',
            'slug' => 'test-lab',
        ]);

        // Create plan with 50 hours/month quota
        $this->plan = Plan::create([
            'name' => 'Test Lab Plan',
            'slug' => 'test-lab',
            'description' => 'Test lab plan with 50 hours',
            'price' => 5000,
            'currency' => 'KES',
            'billing_cycle' => 'monthly',
            'status' => 'active',
        ]);

        // Create user and subscription
        $this->user = User::factory()->create();

        // Create SystemFeature and attach to plan with value of 50 hours
        $systemFeature = \App\Models\SystemFeature::create([
            'name' => 'Lab Hours Monthly',
            'slug' => 'lab_hours_monthly',
            'description' => 'Monthly lab hours allocation',
            'value_type' => 'number',
            'default_value' => '0',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $this->plan->systemFeatures()->attach($systemFeature->id, [
            'value' => '50',
        ]);

        $subscription = PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => 'user',
            'plan_id' => $this->plan->id,
            'slug' => 'test-lab-sub',
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
        ]);

        // Initialize quota for current month
        $this->quotaService->replenishMonthlyQuota($this->user);
    }

    /**
     * TEST GROUP 1: Single-Month Quota Validation
     */

    /**
     * PASS: Validate quota returns success with sufficient quota
     */
    public function test_validate_quota_availability_succeeds_with_sufficient_quota()
    {
        $result = $this->bookingService->validateQuotaAvailability($this->user, 10);

        $this->assertTrue($result['valid']);
        $this->assertStringContainsString('Quota available', $result['message']);
        $this->assertEquals(40, $result['remaining']);
    }

    /**
     * PASS: Validate quota fails when insufficient
     */
    public function test_validate_quota_availability_fails_when_insufficient()
    {
        $result = $this->bookingService->validateQuotaAvailability($this->user, 60);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Insufficient quota', $result['message']);
        $this->assertEquals(50, $result['remaining']);
    }

    /**
     * PASS: Validate quota fails without active subscription
     */
    public function test_validate_quota_fails_without_subscription()
    {
        $userNoSub = User::factory()->create();

        $result = $this->bookingService->validateQuotaAvailability($userNoSub, 10);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('active lab subscription', $result['message']);
    }

    /**
     * PASS: Validate quota for specific month
     */
    public function test_validate_quota_for_specific_month()
    {
        $nextMonth = now()->addMonth();
        QuotaCommitment::create([
            'user_id' => $this->user->id,
            'month_date' => $nextMonth->startOfMonth(),
            'committed_hours' => 50,
            'used_hours' => 45,
            'replenished_at' => now(),
        ]);

        $result = $this->bookingService->validateQuotaAvailability($this->user, 10, $nextMonth);

        $this->assertFalse($result['valid']); // Only 5 hours available
        $this->assertEquals(5, $result['remaining']);
    }

    /**
     * PASS: Validate quota with exact amount
     */
    public function test_validate_quota_with_exact_remaining_amount()
    {
        $result = $this->bookingService->validateQuotaAvailability($this->user, 50);

        $this->assertTrue($result['valid']);
        $this->assertEquals(0, $result['remaining']);
    }

    /**
     * TEST GROUP 2: Recurring Quota Validation (Multi-Month)
     */

    /**
     * PASS: Validate recurring quota across multiple months with sufficient quota
     */
    public function test_validate_recurring_quota_succeeds_with_sufficient_quota()
    {
        // Set up quotas for 3 months with 10 hours each already used
        for ($i = 0; $i < 3; $i++) {
            $month = now()->addMonths($i)->startOfMonth();
            $commitment = QuotaCommitment::firstOrCreate(
                ['user_id' => $this->user->id, 'month_date' => $month],
                [
                    'committed_hours' => 50,
                    'used_hours' => 10,
                    'replenished_at' => now(),
                ]
            );
        }

        // Validate 15 hours per month for 3 months
        $result = $this->bookingService->validateRecurringQuotaAvailability(
            $this->user,
            15,
            now(),
            now()->addMonths(2)
        );

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['months_short']);
    }

    /**
     * FAIL: Validate recurring quota fails when insufficient in one month
     */
    public function test_validate_recurring_quota_fails_when_insufficient_in_any_month()
    {
        // Set up quotas
        QuotaCommitment::updateOrCreate(
            ['user_id' => $this->user->id, 'month_date' => now()->startOfMonth()],
            [
                'committed_hours' => 50,
                'used_hours' => 30, // 20 remaining
                'replenished_at' => now(),
            ]
        );

        QuotaCommitment::updateOrCreate(
            ['user_id' => $this->user->id, 'month_date' => now()->addMonth()->startOfMonth()],
            [
                'committed_hours' => 50,
                'used_hours' => 40, // 10 remaining (insufficient!)
                'replenished_at' => now(),
            ]
        );

        // Try to validate 15 hours per month
        $result = $this->bookingService->validateRecurringQuotaAvailability(
            $this->user,
            15,
            now(),
            now()->addMonth()
        );

        $this->assertFalse($result['valid']);
        $this->assertCount(1, $result['months_short']);
        $this->assertEquals(now()->addMonth()->format('Y-m'), $result['months_short'][0]['month']);
        $this->assertEquals(10, $result['months_short'][0]['available']);
        $this->assertEquals(15, $result['months_short'][0]['needed']);
    }

    /**
     * FAIL: Validate recurring quota fails when insufficient in multiple months
     */
    public function test_validate_recurring_quota_fails_when_insufficient_in_multiple_months()
    {
        // Set up quotas with insufficient availability in 2 months
        for ($i = 0; $i < 3; $i++) {
            $month = now()->addMonths($i)->startOfMonth();
            QuotaCommitment::updateOrCreate(
                ['user_id' => $this->user->id, 'month_date' => $month],
                [
                    'committed_hours' => 50,
                    'used_hours' => 40 + ($i * 5), // 10, 5, 0 remaining
                    'replenished_at' => now(),
                ]
            );
        }

        $result = $this->bookingService->validateRecurringQuotaAvailability(
            $this->user,
            15,
            now(),
            now()->addMonths(2)
        );

        $this->assertFalse($result['valid']);
        $this->assertCount(3, $result['months_short']); // 3 months short
    }

    /**
     * TEST GROUP 3: Quota Consumption During Booking
     */

    /**
     * PASS: Consume booking quota deducts hours
     */
    public function test_consume_booking_quota_deducts_hours()
    {
        $booking = LabBooking::create([
            'lab_space_id' => $this->lab->id,
            'user_id' => $this->user->id,
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(5),
            'duration_hours' => 3,
            'status' => LabBooking::STATUS_CONFIRMED,
            'purpose' => 'Test',
        ]);

        $result = $this->bookingService->consumeBookingQuota($booking);

        $this->assertTrue($result);

        $status = $this->quotaService->getQuotaStatus($this->user);
        $this->assertEquals(3, $status['used_hours']);
        $this->assertEquals(47, $status['remaining_hours']);
    }

    /**
     * PASS: Consume booking quota fails when insufficient
     */
    public function test_consume_booking_quota_fails_when_insufficient()
    {
        $booking = LabBooking::create([
            'lab_space_id' => $this->lab->id,
            'user_id' => $this->user->id,
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(60),
            'duration_hours' => 58, // More than available quota
            'status' => LabBooking::STATUS_CONFIRMED,
            'purpose' => 'Test',
        ]);

        $result = $this->bookingService->consumeBookingQuota($booking);

        $this->assertFalse($result);

        $status = $this->quotaService->getQuotaStatus($this->user);
        $this->assertEquals(0, $status['used_hours']); // No quota consumed
    }

    /**
     * PASS: Guest bookings don't consume quota
     */
    public function test_guest_booking_does_not_consume_quota()
    {
        $booking = LabBooking::create([
            'lab_space_id' => $this->lab->id,
            'guest_email' => 'guest@example.com',
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(5),
            'duration_hours' => 3,
            'status' => LabBooking::STATUS_CONFIRMED,
            'purpose' => 'Test',
        ]);

        $result = $this->bookingService->consumeBookingQuota($booking);

        $this->assertTrue($result); // Returns true for guests (no-op)

        $status = $this->quotaService->getQuotaStatus($this->user);
        $this->assertEquals(0, $status['used_hours']); // No quota consumed
    }

    /**
     * PASS: Multiple bookings consume quota sequentially
     */
    public function test_multiple_bookings_consume_quota_sequentially()
    {
        // First booking: 10 hours
        $booking1 = LabBooking::create([
            'lab_space_id' => $this->lab->id,
            'user_id' => $this->user->id,
            'starts_at' => now()->addHours(1),
            'ends_at' => now()->addHours(11),
            'duration_hours' => 10,
            'status' => LabBooking::STATUS_CONFIRMED,
            'purpose' => 'Test',
        ]);
        $this->bookingService->consumeBookingQuota($booking1);

        // Second booking: 15 hours
        $booking2 = LabBooking::create([
            'lab_space_id' => $this->lab->id,
            'user_id' => $this->user->id,
            'starts_at' => now()->addHours(20),
            'ends_at' => now()->addHours(35),
            'duration_hours' => 15,
            'status' => LabBooking::STATUS_CONFIRMED,
            'purpose' => 'Test',
        ]);
        $this->bookingService->consumeBookingQuota($booking2);

        // Third booking: 25 hours
        $booking3 = LabBooking::create([
            'lab_space_id' => $this->lab->id,
            'user_id' => $this->user->id,
            'starts_at' => now()->addHours(40),
            'ends_at' => now()->addHours(65),
            'duration_hours' => 25,
            'status' => LabBooking::STATUS_CONFIRMED,
            'purpose' => 'Test',
        ]);
        $this->bookingService->consumeBookingQuota($booking3);

        $status = $this->quotaService->getQuotaStatus($this->user);
        $this->assertEquals(50, $status['used_hours']); // All quota consumed
        $this->assertEquals(0, $status['remaining_hours']);
    }

    /**
     * TEST GROUP 4: Quota Release (Cancellation)
     */

    /**
     * PASS: Release booking quota returns hours to commitment
     */
    public function test_release_booking_quota_returns_hours()
    {
        $booking = LabBooking::create([
            'lab_space_id' => $this->lab->id,
            'user_id' => $this->user->id,
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(8),
            'duration_hours' => 6,
            'status' => LabBooking::STATUS_CONFIRMED,
            'purpose' => 'Test',
        ]);

        // Consume quota
        $this->bookingService->consumeBookingQuota($booking);
        $status = $this->quotaService->getQuotaStatus($this->user);
        $this->assertEquals(6, $status['used_hours']);

        // Release quota
        $result = $this->bookingService->releaseBookingQuota($booking);
        $this->assertTrue($result);

        $status = $this->quotaService->getQuotaStatus($this->user);
        $this->assertEquals(0, $status['used_hours']);
        $this->assertEquals(50, $status['remaining_hours']);
    }

    /**
     * PASS: Release booking quota fails if no commitment exists
     */
    public function test_release_booking_quota_fails_without_commitment()
    {
        $pastMonth = now()->subMonth()->startOfMonth();
        $booking = LabBooking::create([
            'lab_space_id' => $this->lab->id,
            'user_id' => $this->user->id,
            'starts_at' => $pastMonth->copy()->addHours(2),
            'ends_at' => $pastMonth->copy()->addHours(8),
            'duration_hours' => 6,
            'status' => LabBooking::STATUS_CONFIRMED,
            'purpose' => 'Test',
        ]);

        $result = $this->bookingService->releaseBookingQuota($booking);
        $this->assertFalse($result); // No commitment for past month
    }

    /**
     * PASS: Guest booking release is no-op
     */
    public function test_release_guest_booking_quota_is_noop()
    {
        $booking = LabBooking::create([
            'lab_space_id' => $this->lab->id,
            'guest_email' => 'guest@example.com',
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(8),
            'duration_hours' => 6,
            'status' => LabBooking::STATUS_CONFIRMED,
            'purpose' => 'Test',
        ]);

        $result = $this->bookingService->releaseBookingQuota($booking);
        $this->assertTrue($result); // No-op returns true
    }

    /**
     * PASS: Cancel and release quota workflow
     */
    public function test_cancel_booking_workflow_with_quota_release()
    {
        $booking = LabBooking::create([
            'lab_space_id' => $this->lab->id,
            'user_id' => $this->user->id,
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(10),
            'duration_hours' => 8,
            'status' => LabBooking::STATUS_CONFIRMED,
            'purpose' => 'Test',
        ]);

        // Consume quota
        $this->bookingService->consumeBookingQuota($booking);
        $this->assertEquals(8, $this->quotaService->getQuotaStatus($this->user)['used_hours']);

        // Cancel booking and release quota
        $booking->update(['status' => LabBooking::STATUS_CANCELLED]);
        $this->bookingService->releaseBookingQuota($booking);

        $status = $this->quotaService->getQuotaStatus($this->user);
        $this->assertEquals(0, $status['used_hours']);
        $this->assertEquals(50, $status['remaining_hours']);
    }

    /**
     * TEST GROUP 5: Edge Cases
     */

    /**
     * PASS: Booking at 100% quota (exact match)
     */
    public function test_booking_at_exact_quota_limit()
    {
        $result = $this->bookingService->validateQuotaAvailability($this->user, 50);
        $this->assertTrue($result['valid']);

        $booking = LabBooking::create([
            'lab_space_id' => $this->lab->id,
            'user_id' => $this->user->id,
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(52),
            'duration_hours' => 50,
            'status' => LabBooking::STATUS_CONFIRMED,
            'purpose' => 'Test',
        ]);

        $consumed = $this->bookingService->consumeBookingQuota($booking);
        $this->assertTrue($consumed);

        $status = $this->quotaService->getQuotaStatus($this->user);
        $this->assertTrue($status['remaining_hours'] === 0.0);
    }

    /**
     * PASS: Get monthly quota status via booking service
     */
    public function test_get_monthly_quota_status_via_booking_service()
    {
        $status = $this->bookingService->getMonthlyQuotaStatus($this->user);

        $this->assertArrayHasKey('total_hours', $status);
        $this->assertArrayHasKey('remaining_hours', $status);
        $this->assertArrayHasKey('percentage_used', $status);
        $this->assertEquals(50, $status['total_hours']);
        $this->assertEquals(50, $status['remaining_hours']);
    }
}
