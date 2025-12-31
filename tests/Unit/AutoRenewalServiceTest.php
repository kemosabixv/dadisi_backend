<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\SubscriptionEnhancement;
use App\Services\AutoRenewalService;
use App\Services\PaymentGateway\GatewayManager;
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentFailedFinalMail;

class AutoRenewalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure migrations are applied in the testing sqlite database
        $this->artisan('migrate');
    }

    public function test_successful_renewal_extends_subscription_and_records_job()
    {
        // Arrange
        $user = User::create([
            'username' => 'jdoe',
            'email' => 'jdoe@example.com',
            'password' => bcrypt('password'),
        ]);

        $plan = Plan::create([
            'name' => ['en' => 'Test Plan'],
            'slug' => 'test-plan',
            'price' => 1000,
            'currency' => 'KES',
        ]);

        $subscription = PlanSubscription::create([
            'subscriber_type' => User::class,
            'subscriber_id' => $user->id,
            'plan_id' => $plan->id,
            'name' => ['en' => 'Test Plan Subscription'],
            'slug' => 'test-sub-' . uniqid(),
            'starts_at' => now(),
            'ends_at' => now()->addDays(1),
        ]);

        $enh = SubscriptionEnhancement::create([
            'subscription_id' => $subscription->id,
            'status' => 'payment_pending',
            'payment_method' => '254701234567', // success pattern
            'renewal_attempts' => 0,
            'max_renewal_attempts' => 3,
        ]);

        // Ensure subscriber relation is available for service
        $subscription->setRelation('user', $user);

        // Act
        $gatewayManager = app(GatewayManager::class);
        $service = new AutoRenewalService($gatewayManager);
        $job = $service->processSubscriptionRenewal($subscription);

        // Assert
        $this->assertEquals('succeeded', $job->status);

        $subscription->refresh();
        $this->assertTrue($subscription->ends_at->greaterThan(now()->addDays(25))); // extended by ~1 month
    }

    public function test_failed_renewal_sends_final_email_on_third_attempt()
    {
        Mail::fake();

        // Arrange
        $user = User::create([
            'username' => 'failure',
            'email' => 'failure@example.com',
            'password' => bcrypt('password'),
        ]);

        $plan = Plan::create([
            'name' => ['en' => 'Fail Plan'],
            'slug' => 'fail-plan',
            'price' => 1000,
            'currency' => 'KES',
        ]);

        $subscription = PlanSubscription::create([
            'subscriber_type' => User::class,
            'subscriber_id' => $user->id,
            'plan_id' => $plan->id,
            'name' => ['en' => 'Fail Subscription'],
            'slug' => 'fail-sub-' . uniqid(),
            'starts_at' => now(),
            'ends_at' => now()->addDays(1),
        ]);

        $enh = SubscriptionEnhancement::create([
            'subscription_id' => $subscription->id,
            'status' => 'payment_pending',
            'payment_method' => '254709999999', // explicit failure
            'renewal_attempts' => 2, // already attempted twice
            'max_renewal_attempts' => 3,
        ]);

        // Ensure subscriber relation is available for service
        $subscription->setRelation('user', $user);

        // Act
        $gatewayManager = app(GatewayManager::class);
        $service = new AutoRenewalService($gatewayManager);
        $job = $service->processSubscriptionRenewal($subscription);

        // Assert job failed and email queued
        $this->assertEquals('failed', $job->status);

        Mail::assertQueued(PaymentFailedFinalMail::class);
    }
}
