<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\SubscriptionEnhancement;
use App\Services\AutoRenewalService;
use App\Mail\PaymentFailedFinalMail;

class RenewalRetrySchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_retry_scheduling_increments_and_sets_next_retry()
    {
        // Arrange
        $user = User::create([
            'username' => 'retryuser',
            'email' => 'retry@example.com',
            'password' => bcrypt('password'),
        ]);

        $plan = Plan::create([
            'name' => ['en' => 'Retry Plan'],
            'slug' => 'retry-plan',
            'price' => 500,
            'currency' => 'KES',
        ]);

        $subscription = PlanSubscription::create([
            'subscriber_type' => User::class,
            'subscriber_id' => $user->id,
            'plan_id' => $plan->id,
            'name' => ['en' => 'Retry Subscription'],
            'slug' => 'retry-sub-' . uniqid(),
            'starts_at' => now(),
            'ends_at' => now()->addDays(1),
        ]);

        $enh = SubscriptionEnhancement::create([
            'subscription_id' => $subscription->id,
            'status' => 'payment_pending',
            'payment_method' => '254709999999', // explicit failure
            'renewal_attempts' => 0,
            'max_renewal_attempts' => 3,
        ]);

        $subscription->setRelation('user', $user);

        $service = new AutoRenewalService();

        // Act & Assert - first failure -> 24h
        $start = now();
        $job1 = $service->processSubscriptionRenewal($subscription);
        $this->assertEquals('failed', $job1->status);

        $enh->refresh();
        $this->assertEquals(1, $enh->renewal_attempt_count);
        $this->assertEquals('failed', $enh->last_renewal_result);
        $this->assertNotNull($job1->next_retry_at);
        $this->assertEquals('retry_24h', $job1->attempt_type);
        // allow some leeway for timestamp rounding — expect ~24 hours
        $this->assertTrue($enh->next_auto_renewal_at->greaterThanOrEqualTo($start->copy()->addHours(22)));
        $this->assertTrue($enh->next_auto_renewal_at->lessThanOrEqualTo($start->copy()->addHours(26)));

        // Act & Assert - second failure -> 3d
        $job2 = $service->processSubscriptionRenewal($subscription);
        $this->assertEquals('failed', $job2->status);

        $enh->refresh();
        $this->assertEquals(2, $enh->renewal_attempt_count);
        $this->assertEquals('retry_3d', $job2->attempt_type);
        $this->assertTrue($enh->next_auto_renewal_at->greaterThanOrEqualTo($start->copy()->addDays(2)->subHours(6)));
        $this->assertTrue($enh->next_auto_renewal_at->lessThanOrEqualTo($start->copy()->addDays(3)->addHours(6)));

        // Act & Assert - third failure -> 7d and final email
        Mail::fake();
        $job3 = $service->processSubscriptionRenewal($subscription);
        $this->assertEquals('failed', $job3->status);

        $enh->refresh();
        $this->assertEquals(3, $enh->renewal_attempt_count);
        $this->assertEquals('retry_7d', $job3->attempt_type);
        $this->assertTrue($enh->next_auto_renewal_at->greaterThanOrEqualTo($start->copy()->addDays(6)->subHours(12)));
        $this->assertTrue($enh->next_auto_renewal_at->lessThanOrEqualTo($start->copy()->addDays(8)->addHours(12)));

        Mail::assertQueued(PaymentFailedFinalMail::class);
    }

    public function test_final_failure_queues_email_when_legacy_attempts_set()
    {
        Mail::fake();

        // Arrange
        $user = User::create([
            'username' => 'legacy',
            'email' => 'legacy@example.com',
            'password' => bcrypt('password'),
        ]);

        $plan = Plan::create([
            'name' => ['en' => 'Legacy Plan'],
            'slug' => 'legacy-plan',
            'price' => 700,
            'currency' => 'KES',
        ]);

        $subscription = PlanSubscription::create([
            'subscriber_type' => User::class,
            'subscriber_id' => $user->id,
            'plan_id' => $plan->id,
            'name' => ['en' => 'Legacy Subscription'],
            'slug' => 'legacy-sub-' . uniqid(),
            'starts_at' => now(),
            'ends_at' => now()->addDays(1),
        ]);

        // Use legacy renewal_attempts to simulate two prior attempts
        $enh = SubscriptionEnhancement::create([
            'subscription_id' => $subscription->id,
            'status' => 'payment_pending',
            'payment_method' => '254709999999', // explicit failure
            'renewal_attempts' => 2, // legacy counter
            'max_renewal_attempts' => 3,
        ]);

        $subscription->setRelation('user', $user);

        $service = new AutoRenewalService();

        // Act
        $job = $service->processSubscriptionRenewal($subscription);

        // Assert
        $this->assertEquals('failed', $job->status);
        Mail::assertQueued(PaymentFailedFinalMail::class);
    }
}
