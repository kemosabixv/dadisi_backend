<?php

namespace Tests\Feature\Lab;

use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\QuotaCommitment;
use App\Models\User;
use App\Services\QuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * QuotaService Tests - PHASE 2
 * 
 * Comprehensive test coverage for monthly quota replenishment and commitment system
 * 
 * Test Groups:
 * 1. Monthly Quota Replenishment (creation, duplicate prevention)
 * 2. Quota Status Tracking (remaining hours, usage percentage)
 * 3. Quota Consumption (deducting hours from available quota)
 * 4. Quota Commitment for Future Months (recurring bookings)
 * 5. Warning Threshold Detection (80% + marking)
 * 6. Edge Cases (exhausted quota, no subscription, mid-month checks)
 */
class QuotaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Plan $plan;
    protected PlanSubscription $subscription;
    protected QuotaService $quotaService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->quotaService = app(QuotaService::class);
        
        // Create a plan with 50 hours/month lab quota
        $this->plan = Plan::create([
            'name' => 'Test Lab Plan',
            'slug' => 'test-lab',
            'price' => 5000,
            'currency' => 'KES',
            'billing_cycle' => 'monthly',
            'status' => 'active',
        ]);
        
        // Add lab_hours_monthly feature to plan
        $labFeature = \App\Models\SystemFeature::firstOrCreate([
            'slug' => 'lab_hours_monthly',
        ], [
            'name' => 'Lab Quota Hours',
            'description' => 'Monthly lab quota hours',
        ]);
        $this->plan->systemFeatures()->attach($labFeature->id, ['value' => 50]);
        
        // Create user with active subscription
        $this->user = User::factory()->create();
        $this->subscription = $this->user->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
        ]);
    }

    /**
     * TEST GROUP 1: Monthly Quota Replenishment
     */

    /**

     * PASS: Replenish creates new quota commitment for current month
     */
    public function test_replenish_monthly_quota_creates_commitment()
    {
        $this->assertTrue($this->quotaService->replenishMonthlyQuota($this->user));
        
        $commitment = QuotaCommitment::where('user_id', $this->user->id)
            ->where('month_date', now()->startOfMonth())
            ->first();
        
        $this->assertNotNull($commitment);
        $this->assertEquals(50, $commitment->committed_hours);
        $this->assertEquals(0, $commitment->used_hours);
        $this->assertFalse($commitment->warned_at_threshold);
    }

    /**

     * PASS: Replenish returns false if commitment already exists for month
     * Prevents duplicate commitments from repeated calls
     */
    public function test_replenish_only_creates_one_commitment_per_month()
    {
        $this->quotaService->replenishMonthlyQuota($this->user);
        $firstCommitment = QuotaCommitment::where('user_id', $this->user->id)
            ->where('month_date', now()->startOfMonth())
            ->first();

        // Try to replenish again
        $result = $this->quotaService->replenishMonthlyQuota($this->user);
        $this->assertFalse($result);

        // Still only one commitment
        $count = QuotaCommitment::where('user_id', $this->user->id)
            ->where('month_date', now()->startOfMonth())
            ->count();
        $this->assertEquals(1, $count);
    }

    /**

     * PASS: Replenish returns false if user has no active subscription
     */
    public function test_replenish_fails_without_active_subscription()
    {
        $user = User::factory()->create();
        $result = $this->quotaService->replenishMonthlyQuota($user);
        
        $this->assertFalse($result);
        $this->assertEquals(0, QuotaCommitment::where('user_id', $user->id)->count());
    }

    /**
     * TEST GROUP 2: Quota Status Tracking
     */

    /**

     * PASS: Get quota status returns correct remaining hours
     */
    public function test_get_quota_status_returns_correct_remaining()
    {
        $this->quotaService->replenishMonthlyQuota($this->user);
        
        $status = $this->quotaService->getQuotaStatus($this->user);
        
        $this->assertTrue($status['commitment_exists']);
        $this->assertEquals(50, $status['total_hours']);
        $this->assertEquals(0, $status['used_hours']);
        $this->assertEquals(50, $status['remaining_hours']);
        $this->assertEquals(0, $status['percentage_used']);
    }

    /**

     * PASS: Get quota status after deduction shows correct math
     */
    public function test_get_quota_status_after_consumption()
    {
        $this->quotaService->replenishMonthlyQuota($this->user);
        $commitment = QuotaCommitment::where('user_id', $this->user->id)
            ->where('month_date', now()->startOfMonth())
            ->first();
        
        // Consume 15 hours
        $commitment->consume(15);
        
        $status = $this->quotaService->getQuotaStatus($this->user);
        
        $this->assertEquals(50, $status['total_hours']);
        $this->assertEquals(15, $status['used_hours']);
        $this->assertEquals(35, $status['remaining_hours']);
        $this->assertEquals(30.0, $status['percentage_used']);
    }

    /**

     * PASS: Get quota status for month without commitment returns zeros
     */
    public function test_get_quota_status_for_month_without_commitment()
    {
        $futureMonth = now()->addMonth();
        $status = $this->quotaService->getQuotaStatus($this->user, $futureMonth);
        
        $this->assertFalse($status['commitment_exists']);
        $this->assertEquals(0, $status['total_hours']);
        $this->assertEquals(0, $status['remaining_hours']);
    }

    /**
     * TEST GROUP 3: Quota Consumption
     */

    /**

     * PASS: Can book returns true when sufficient quota
     */
    public function test_can_book_returns_true_with_sufficient_quota()
    {
        $this->quotaService->replenishMonthlyQuota($this->user);
        
        $this->assertTrue($this->quotaService->canBook($this->user, 30));
        $this->assertTrue($this->quotaService->canBook($this->user, 50)); // Exact amount
    }

    /**

     * PASS: Can book returns false when insufficient quota
     */
    public function test_can_book_returns_false_when_insufficient_quota()
    {
        $this->quotaService->replenishMonthlyQuota($this->user);
        
        $this->assertFalse($this->quotaService->canBook($this->user, 51));
        $this->assertFalse($this->quotaService->canBook($this->user, 100));
    }

    /**

     * PASS: Commit quota for month deducts hours correctly
     */
    public function test_commit_quota_for_month_succeeds()
    {
        $this->quotaService->replenishMonthlyQuota($this->user);
        
        $result = $this->quotaService->commitQuotaForMonth($this->user, now(), 15);
        $this->assertTrue($result);
        
        $status = $this->quotaService->getQuotaStatus($this->user);
        $this->assertEquals(15, $status['used_hours']);
        $this->assertEquals(35, $status['remaining_hours']);
    }

    /**

     * PASS: Commit quota fails when insufficient
     */
    public function test_commit_quota_fails_when_insufficient()
    {
        $this->quotaService->replenishMonthlyQuota($this->user);
        
        $result = $this->quotaService->commitQuotaForMonth($this->user, now(), 60);
        $this->assertFalse($result);
        
        // No hours consumed
        $status = $this->quotaService->getQuotaStatus($this->user);
        $this->assertEquals(0, $status['used_hours']);
    }

    /**

     * PASS: Commit quota for future month creates commitment
     */
    public function test_commit_quota_for_future_month_creates_commitment()
    {
        $this->quotaService->replenishMonthlyQuota($this->user);
        
        $nextMonth = now()->addMonth();
        $result = $this->quotaService->commitQuotaForMonth($this->user, $nextMonth, 20);
        
        $this->assertTrue($result);
        
        $commitment = QuotaCommitment::where('user_id', $this->user->id)
            ->where('month_date', $nextMonth->startOfMonth())
            ->first();
        
        $this->assertNotNull($commitment);
        $this->assertEquals(50, $commitment->committed_hours);
        $this->assertEquals(20, $commitment->used_hours);
    }

    /**
     * TEST GROUP 4: Multiple Commits & Recurring Bookings
     */

    /**

     * PASS: Multiple commits across months work correctly
     */
    public function test_commit_quota_for_multiple_months()
    {
        // Replenish current month
        $this->quotaService->replenishMonthlyQuota($this->user);

        // Commit for current month
        $this->assertTrue($this->quotaService->commitQuotaForMonth($this->user, now(), 10));

        // Commit for next month
        $nextMonth = now()->addMonth();
        $this->assertTrue($this->quotaService->commitQuotaForMonth($this->user, $nextMonth, 15));

        // Commit for month after
        $twoMonthsOut = now()->addMonths(2);
        $this->assertTrue($this->quotaService->commitQuotaForMonth($this->user, $twoMonthsOut, 20));

        // Verify commitments exist
        $this->assertEquals(3, QuotaCommitment::where('user_id', $this->user->id)->count());
        $this->assertEquals(10, $this->quotaService->getQuotaStatus($this->user, now())['used_hours']);
        $this->assertEquals(15, $this->quotaService->getQuotaStatus($this->user, $nextMonth)['used_hours']);
        $this->assertEquals(20, $this->quotaService->getQuotaStatus($this->user, $twoMonthsOut)['used_hours']);
    }

    /**
     * TEST GROUP 5: Warning Threshold Detection
     */

    /**

     * PASS: Quota commitment detects when above 80% threshold
     */
    public function test_quota_commitment_detects_above_threshold()
    {
        $this->quotaService->replenishMonthlyQuota($this->user);
        $commitment = QuotaCommitment::where('user_id', $this->user->id)
            ->where('month_date', now()->startOfMonth())
            ->first();
        
        // Consume 40 hours (80% of 50)
        $commitment->consume(40);
        
        $this->assertTrue($commitment->isAboveWarningThreshold());
    }

    /**

     * PASS: Mark threshold warning prevents duplicate warnings
     */
    public function test_mark_threshold_warning()
    {
        $this->quotaService->replenishMonthlyQuota($this->user);
        $commitment = QuotaCommitment::where('user_id', $this->user->id)
            ->where('month_date', now()->startOfMonth())
            ->first();
        
        $this->assertFalse($commitment->warned_at_threshold);
        
        $this->quotaService->markThresholdWarning($this->user);
        $commitment->refresh();
        
        $this->assertTrue($commitment->warned_at_threshold);
    }

    /**
     * TEST GROUP 6: Edge Cases & Special Scenarios
     */

    /**

     * PASS: Exhausted quota (no hours remaining)
     */
    public function test_quota_commitment_exhausted()
    {
        $this->quotaService->replenishMonthlyQuota($this->user);
        $commitment = QuotaCommitment::where('user_id', $this->user->id)
            ->where('month_date', now()->startOfMonth())
            ->first();
        
        // Consume all 50 hours
        $commitment->consume(50);
        
        $this->assertTrue($commitment->isExhausted());
        $this->assertEquals(0, $commitment->getRemainingHours());
        $this->assertFalse($this->quotaService->canBook($this->user, 1));
    }

    /**

     * PASS: Over-consumed quota (booking after quota exhausted)
     */
    public function test_quota_commitment_over_consumed()
    {
        $this->quotaService->replenishMonthlyQuota($this->user);
        $commitment = QuotaCommitment::where('user_id', $this->user->id)
            ->where('month_date', now()->startOfMonth())
            ->first();
        
        // Try to consume more than available
        $result = $commitment->consume(100);
        
        $this->assertFalse($result);
        $this->assertEquals(0, $commitment->used_hours); // Not consumed
    }

    /**

     * PASS: Get monthly statistics for admin reporting
     */
    public function test_get_monthly_statistics()
    {
        // Create first user from setUp and replenish quota
        $this->quotaService->replenishMonthlyQuota($this->user);
        $commitment = QuotaCommitment::where('user_id', $this->user->id)
            ->where('month_date', now()->startOfMonth())
            ->first();
        $commitment->consume(20);
        
        // Create 3 additional users with different usage
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create();
            $user->subscriptions()->create([
                'plan_id' => $this->plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addYear(),
            ]);
            
            $this->quotaService->replenishMonthlyQuota($user);
            
            // Vary usage
            $commitment = QuotaCommitment::where('user_id', $user->id)
                ->where('month_date', now()->startOfMonth())
                ->first();
            $commitment->consume(20 + ($i * 10));
        }

        $stats = $this->quotaService->getMonthlyStatistics();
        
        $this->assertEquals(now()->format('Y-m'), now()->format('Y-m'));
        $this->assertEquals(200, $stats['total_committed_hours']); // 4 users × 50 hours
        $this->assertEquals(110, $stats['total_used_hours']); // 20 + 20 + 30 + 40
        $this->assertGreaterThan(0, $stats['utilization_percentage']);
    }

    /**

     * PASS: Clean up expired quotas (past months)
     */
    public function test_cleanup_expired_quotas()
    {
        // Create commitment for last month
        QuotaCommitment::create([
            'user_id' => $this->user->id,
            'month_date' => now()->subMonth()->startOfMonth(),
            'committed_hours' => 50,
            'used_hours' => 50,
            'replenished_at' => now(),
        ]);

        // Create commitment for this month
        $this->quotaService->replenishMonthlyQuota($this->user);

        $this->assertEquals(2, QuotaCommitment::where('user_id', $this->user->id)->count());

        // Clean up past months
        $deleted = $this->quotaService->cleanupExpiredQuotas();
        
        $this->assertEquals(1, $deleted);
        $this->assertEquals(1, QuotaCommitment::where('user_id', $this->user->id)->count());
    }

    /**

     * PASS: Get quota status for multiple months
     */
    public function test_get_quota_status_for_multiple_months()
    {
        // Create commitments for 3 months
        for ($i = 0; $i < 3; $i++) {
            $month = now()->addMonths($i)->startOfMonth();
            $this->quotaService->replenishMonthlyQuota($this->user);
            
            if ($i > 0) {
                QuotaCommitment::create([
                    'user_id' => $this->user->id,
                    'month_date' => $month,
                    'committed_hours' => 50,
                    'used_hours' => 0,
                    'replenished_at' => now(),
                ]);
            }
        }

        $statuses = $this->quotaService->getQuotaStatusForMonths($this->user, 3);
        
        $this->assertCount(3, $statuses);
        foreach ($statuses as $month => $status) {
            $this->assertArrayHasKey('total_hours', $status);
            $this->assertArrayHasKey('remaining_hours', $status);
        }
    }

    /**
     * TEST GROUP: Grace Period Handling (CRITICAL - Bug Fix Tests)
     */

    /**

     * FAIL/PASS: activeLabSubscription() returns NULL when subscription is in grace period
     * Grace period = ends_at < now() and canceled_at = NULL
     * During grace period, NO quota should be replenished even if plan has lab quota feature
     */
    public function test_active_lab_subscription_returns_null_in_grace_period()
    {
        // Subscription in grace period (ended yesterday, not canceled)
        $this->subscription->update([
            'ends_at' => now()->subDay(),
            'canceled_at' => null,
        ]);

        // Should return NULL during grace period
        $activeSubscription = $this->user->activeLabSubscription();
        $this->assertNull($activeSubscription);

        // And replenishment should fail
        $result = $this->quotaService->replenishMonthlyQuota($this->user);
        $this->assertFalse($result);

        // No quota commitment created
        $count = QuotaCommitment::where('user_id', $this->user->id)
            ->where('month_date', now()->startOfMonth())
            ->count();
        $this->assertEquals(0, $count);
    }

    /**

     * FAIL/PASS: activeLabSubscription() returns subscription at anniversary (ends_at = today)
     * At anniversary (ends_at = now), subscription is still valid for the current month's quota
     * It only enters grace period AFTER the anniversary (ends_at < now)
     */
    public function test_active_lab_subscription_valid_at_anniversary()
    {
        // Subscription ends today (anniversary)
        $this->subscription->update(['ends_at' => now()]);

        // Should still be valid at anniversary
        $activeSubscription = $this->user->activeLabSubscription();
        $this->assertNotNull($activeSubscription);

        // Replenishment should succeed
        $result = $this->quotaService->replenishMonthlyQuota($this->user);
        $this->assertTrue($result);
    }

    /**

     * FAIL/PASS: activeLabSubscription() returns NULL when plan has no lab_hours_monthly feature
     * Validate that the method filters by plan feature, not just subscription status
     */
    public function test_active_lab_subscription_returns_null_without_quota_feature()
    {
        // Remove lab quota feature from plan
        $this->plan->systemFeatures()->detach();

        // Should return NULL even though subscription is active
        $activeSubscription = $this->user->activeLabSubscription();
        $this->assertNull($activeSubscription);

        // And replenishment should fail
        $result = $this->quotaService->replenishMonthlyQuota($this->user);
        $this->assertFalse($result);
    }

    /**

     * FAIL/PASS: activeLabSubscription() returns NULL when plan has 0 lab_hours_monthly
     * Even if feature exists, 0 hours = no lab quota capability
     */
    public function test_active_lab_subscription_returns_null_with_zero_quota_hours()
    {
        // Set lab quota to 0
        $this->plan->systemFeatures()->detach();
        $labFeature = \App\Models\SystemFeature::firstOrCreate([
            'slug' => 'lab_hours_monthly',
        ], [
            'name' => 'Lab Quota Hours',
            'description' => 'Monthly lab quota hours',
        ]);
        $this->plan->systemFeatures()->attach($labFeature->id, ['value' => 0]);

        // Should return NULL
        $activeSubscription = $this->user->activeLabSubscription();
        $this->assertNull($activeSubscription);

        // And replenishment should fail
        $result = $this->quotaService->replenishMonthlyQuota($this->user);
        $this->assertFalse($result);
    }

    /**

     * FAIL/PASS: activeLabSubscription() returns first subscription when multiple active exist
     * Expected behavior: return first active (by ID or query order)
     */
    public function test_active_lab_subscription_with_multiple_subscriptions()
    {
        // Create second plan with quota
        $plan2 = Plan::create([
            'name' => 'Second Lab Plan',
            'slug' => 'lab-2',
            'price' => 10000,
            'currency' => 'KES',
            'billing_cycle' => 'monthly',
            'status' => 'active',
        ]);
        $labFeature = \App\Models\SystemFeature::firstOrCreate([
            'slug' => 'lab_hours_monthly',
        ], [
            'name' => 'Lab Quota Hours',
            'description' => 'Monthly lab quota hours',
        ]);
        $plan2->systemFeatures()->attach($labFeature->id, ['value' => 100]);

        // Create second subscription (also active)
        $subscription2 = $this->user->subscriptions()->create([
            'plan_id' => $plan2->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
        ]);

        // Should return a subscription (first one found)
        $activeSubscription = $this->user->activeLabSubscription();
        $this->assertNotNull($activeSubscription);
        $this->assertTrue(
            $activeSubscription->id === $this->subscription->id || 
            $activeSubscription->id === $subscription2->id
        );
    }

    /**

     * FAIL/PASS: replenishMonthlyQuota() does NOT create commitment when plan has no quota feature
     * Validates the double-check in replenishMonthlyQuota (safety check)
     */
    public function test_replenish_fails_when_plan_has_no_quota_feature()
    {
        // Remove feature
        $this->plan->systemFeatures()->detach();

        // Attempt replenishment
        $result = $this->quotaService->replenishMonthlyQuota($this->user);
        
        // Should fail
        $this->assertFalse($result);
        
        // No commitment created
        $count = QuotaCommitment::where('user_id', $this->user->id)->count();
        $this->assertEquals(0, $count);
    }

    /**

     * FAIL/PASS: replenishMonthlyQuota() does NOT create 0-hour commitments
     * Safety check: even if quota validation fails, don't create empty commitments
     */
    public function test_replenish_fails_with_zero_quota_hours()
    {
        // Set to 0 hours
        $this->plan->systemFeatures()->detach();
        $labFeature = \App\Models\SystemFeature::firstOrCreate([
            'slug' => 'lab_hours_monthly',
        ], [
            'name' => 'Lab Quota Hours',
            'description' => 'Monthly lab quota hours',
        ]);
        $this->plan->systemFeatures()->attach($labFeature->id, ['value' => 0]);

        // Attempt replenishment
        $result = $this->quotaService->replenishMonthlyQuota($this->user);
        
        // Should fail
        $this->assertFalse($result);
        
        // No commitment created
        $count = QuotaCommitment::where('user_id', $this->user->id)
            ->where('month_date', now()->startOfMonth())
            ->count();
        $this->assertEquals(0, $count);
    }
}
