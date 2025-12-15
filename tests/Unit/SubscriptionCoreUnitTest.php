<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase as BaseTestCase;
use App\Models\User;
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\RenewalPreference;
use App\Models\SubscriptionEnhancement;

class SubscriptionCoreUnitTest extends BaseTestCase
{
    use RefreshDatabase;

    private User $user;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->plan = Plan::factory()->create([
            'name' => 'Premium',
            'price' => 99.99,
            'is_active' => true,
        ]);
    }

    /**
     * User Model - Subscription Relationship Tests
     */

    #[Test]
    public function test_user_has_many_subscriptions()
    {
        PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => User::class,
            'plan_id' => $this->plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => User::class,
            'plan_id' => $this->plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->assertEquals(2, $this->user->subscriptions()->count());
    }

    #[Test]
    public function test_user_active_subscription_scope()
    {
        $activeSubscription = PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => User::class,
            'plan_id' => $this->plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        $expiredSubscription = PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => User::class,
            'plan_id' => $this->plan->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
            'status' => 'expired',
        ]);

        $active = $this->user->activeSubscription()->first();

        $this->assertNotNull($active);
        $this->assertEquals($activeSubscription->id, $active->id);
        $this->assertEquals('active', $active->status);
    }

    #[Test]
    public function test_user_get_or_create_renewal_preferences()
    {
        $preferences = $this->user->getOrCreateRenewalPreferences();

        $this->assertNotNull($preferences);
        $this->assertEquals($this->user->id, $preferences->user_id);
        $this->assertDatabaseHas('renewal_preferences', [
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function test_user_get_renewal_preferences_returns_existing()
    {
        $created = RenewalPreference::create([
            'user_id' => $this->user->id,
            'renewal_type' => 'manual',
        ]);

        $retrieved = $this->user->getOrCreateRenewalPreferences();

        $this->assertEquals($created->id, $retrieved->id);
        $this->assertEquals('manual', $retrieved->renewal_type);
    }

    /**
     * PlanSubscription Model Tests
     */

    #[Test]
    public function test_plan_subscription_belongs_to_user()
    {
        $subscription = PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => User::class,
            'plan_id' => $this->plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->assertEquals($this->user->id, $subscription->user->id);
    }

    #[Test]
    public function test_plan_subscription_belongs_to_plan()
    {
        $subscription = PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => User::class,
            'plan_id' => $this->plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->assertEquals($this->plan->id, $subscription->plan->id);
    }

    #[Test]
    public function test_plan_subscription_has_many_enhancements()
    {
        $subscription = PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => User::class,
            'plan_id' => $this->plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        SubscriptionEnhancement::create([
            'subscription_id' => $subscription->id,
            'status' => 'active',
        ]);

        SubscriptionEnhancement::create([
            'subscription_id' => $subscription->id,
            'status' => 'pending',
        ]);

        $this->assertEquals(2, $subscription->enhancements()->count());
    }

    #[Test]
    public function test_plan_subscription_is_active()
    {
        $activeSubscription = PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => User::class,
            'plan_id' => $this->plan->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        $expiredSubscription = PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => User::class,
            'plan_id' => $this->plan->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
            'status' => 'expired',
        ]);

        $this->assertTrue($activeSubscription->isActive());
        $this->assertFalse($expiredSubscription->isActive());
    }

    #[Test]
    public function test_plan_subscription_is_expired()
    {
        $expiredSubscription = PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => User::class,
            'plan_id' => $this->plan->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
            'status' => 'expired',
        ]);

        $activeSubscription = PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => User::class,
            'plan_id' => $this->plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        $this->assertTrue($expiredSubscription->isExpired());
        $this->assertFalse($activeSubscription->isExpired());
    }

    #[Test]
    public function test_plan_subscription_days_remaining_until_expiry()
    {
        $subscription = PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => User::class,
            'plan_id' => $this->plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addDays(10),
        ]);

        $daysRemaining = $subscription->daysRemainingUntilExpiry();

        $this->assertGreaterThanOrEqual(9, $daysRemaining);
        $this->assertLessThanOrEqual(11, $daysRemaining);
    }

    #[Test]
    public function test_plan_subscription_cancel()
    {
        $subscription = PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => User::class,
            'plan_id' => $this->plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        $subscription->cancel();

        $this->assertEquals('cancelled', $subscription->fresh()->status);
    }

    /**
     * Plan Model Tests
     */

    #[Test]
    public function test_plan_has_many_subscriptions()
    {
        PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => User::class,
            'plan_id' => $this->plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->assertEquals(1, $this->plan->subscriptions()->count());
    }

    #[Test]
    public function test_plan_active_scope()
    {
        $activePlan = $this->plan;

        $inactivePlan = Plan::factory()->create([
            'is_active' => false,
        ]);

        $activePlans = Plan::active()->get();

        $this->assertContains($activePlan->id, $activePlans->pluck('id')->toArray());
        $this->assertNotContains($inactivePlan->id, $activePlans->pluck('id')->toArray());
    }

    #[Test]
    public function test_plan_get_price()
    {
        $this->assertEquals(99.99, $this->plan->price);
    }

    #[Test]
    public function test_plan_can_be_subscribed_to()
    {
        $this->assertTrue($this->plan->active);

        $inactivePlan = Plan::factory()->create(['is_active' => false]);
        $this->assertFalse($inactivePlan->active);
    }

    /**
     * RenewalPreference Model Tests
     */

    #[Test]
    public function test_renewal_preference_belongs_to_user()
    {
        $preference = RenewalPreference::create([
            'user_id' => $this->user->id,
            'renewal_type' => 'automatic',
        ]);

        $this->assertEquals($this->user->id, $preference->user->id);
    }

    #[Test]
    public function test_renewal_preference_default_values()
    {
        $preference = RenewalPreference::create([
            'user_id' => $this->user->id,
        ]);

        $this->assertEquals('automatic', $preference->renewal_type);
        $this->assertTrue($preference->send_renewal_reminders);
        $this->assertEquals(7, $preference->reminder_days_before);
    }

    #[Test]
    public function test_renewal_preference_can_be_updated()
    {
        $preference = RenewalPreference::create([
            'user_id' => $this->user->id,
            'renewal_type' => 'automatic',
        ]);

        $preference->update([
            'renewal_type' => 'manual',
            'send_renewal_reminders' => false,
        ]);

        $this->assertEquals('manual', $preference->fresh()->renewal_type);
        $this->assertFalse($preference->fresh()->send_renewal_reminders);
    }

    /**
     * SubscriptionEnhancement Model Tests
     */

    #[Test]
    public function test_subscription_enhancement_belongs_to_subscription()
    {
        $subscription = PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $enhancement = SubscriptionEnhancement::create([
            'subscription_id' => $subscription->id,
            'status' => 'active',
        ]);

        $this->assertEquals($subscription->id, $enhancement->subscription->id);
    }

    #[Test]
    public function test_subscription_enhancement_mark_payment_failed()
    {
        $subscription = PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => User::class,
            'plan_id' => $this->plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $enhancement = SubscriptionEnhancement::create([
            'subscription_id' => $subscription->id,
            'status' => 'payment_pending',
        ]);

        $enhancement->markPaymentFailed('retry_immediate', 'Payment declined');

        $this->assertEquals('failed', $enhancement->fresh()->status);
        $this->assertEquals('retry_immediate', $enhancement->fresh()->payment_failure_state);
    }

    #[Test]
    public function test_subscription_enhancement_cancel()
    {
        $subscription = PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => User::class,
            'plan_id' => $this->plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $enhancement = SubscriptionEnhancement::create([
            'subscription_id' => $subscription->id,
            'status' => 'active',
        ]);

        $enhancement->cancel();

        $this->assertEquals('cancelled', $enhancement->fresh()->status);
    }

    #[Test]
    public function test_subscription_enhancement_valid_status_transitions()
    {
        $enhancement = SubscriptionEnhancement::create([
            'subscription_id' => PlanSubscription::create([
                'subscriber_id' => $this->user->id,
                'subscriber_type' => User::class,
                'plan_id' => $this->plan->id,
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
            ])->id,
            'status' => 'payment_pending',
        ]);

        // Valid transition: payment_pending -> active
        $enhancement->update(['status' => 'active']);
        $this->assertEquals('active', $enhancement->fresh()->status);

        // Valid transition: active -> cancelled
        $enhancement->update(['status' => 'cancelled']);
        $this->assertEquals('cancelled', $enhancement->fresh()->status);
    }

    #[Test]
    public function test_subscription_enhancement_renewal_attempts_increment()
    {
        $enhancement = SubscriptionEnhancement::create([
            'subscription_id' => PlanSubscription::create([
                'subscriber_id' => $this->user->id,
                'subscriber_type' => User::class,
                'plan_id' => $this->plan->id,
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
            ])->id,
            'status' => 'active',
            'renewal_attempts' => 0,
            'max_renewal_attempts' => 3,
        ]);

        $enhancement->increment('renewal_attempts');
        $this->assertEquals(1, $enhancement->fresh()->renewal_attempts);

        $enhancement->increment('renewal_attempts', 2);
        $this->assertEquals(3, $enhancement->fresh()->renewal_attempts);
    }

    /**
     * Subscription Business Logic Tests
     */

    #[Test]
    public function test_create_free_tier_subscription()
    {
        $freePlan = Plan::factory()->create([
            'name' => 'Free',
            'price' => 0,
            'is_active' => true,
        ]);

        $subscription = PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => User::class,
            'plan_id' => $freePlan->id,
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
        ]);

        $this->assertNotNull($subscription);
        $this->assertEquals(0, $subscription->plan->price);
    }

    #[Test]
    public function test_multiple_subscriptions_only_one_active()
    {
        $oldSubscription = PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => User::class,
            'plan_id' => $this->plan->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
            'status' => 'expired',
        ]);

        $newSubscription = PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => User::class,
            'plan_id' => $this->plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        $activeSubscriptions = $this->user->activeSubscription();

        $this->assertEquals(1, $activeSubscriptions->count());
        $this->assertEquals($newSubscription->id, $activeSubscriptions->first()->id);
    }

    #[Test]
    public function test_subscription_expiration_calculation()
    {
        $now = now();
        $subscription = PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => User::class,
            'plan_id' => $this->plan->id,
            'starts_at' => $now,
            'ends_at' => $now->copy()->addMonth(),
        ]);

        $daysUntilExpiry = $subscription->daysRemainingUntilExpiry();

        $this->assertGreaterThanOrEqual(29, $daysUntilExpiry);
        $this->assertLessThanOrEqual(31, $daysUntilExpiry);
    }
}
