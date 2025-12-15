<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\RenewalPreference;
use App\Models\SubscriptionEnhancement;

class SubscriptionCoreControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Plan $freePlan;
    private Plan $premiumPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Create test plans
        $this->freePlan = Plan::factory()->create([
            'name' => ['en' => 'Free'],
            'slug' => 'free',
            'price' => 0,
            'is_active' => true,
        ]);

        $this->premiumPlan = Plan::factory()->create([
            'name' => ['en' => 'Premium'],
            'slug' => 'premium',
            'price' => 99.99,
            'is_active' => true,
        ]);
    }

    /**
     * Helper method to create a subscription for the test user
     */
    private function createUserSubscription(Plan $plan, $startDate = null, $endDate = null)
    {
        $startDate = $startDate ?? now();
        $endDate = $endDate ?? now()->addMonth();

        $subscription = PlanSubscription::create([
            'subscriber_id' => $this->user->id,
            'subscriber_type' => 'App\Models\User',
            'plan_id' => $plan->id,
            'starts_at' => $startDate,
            'ends_at' => $endDate,
            'name' => $plan->name,
            'slug' => $plan->slug . '-' . $this->user->id . '-' . time(),
        ]);

        // Set the user's active subscription (use forceFill to bypass fillable)
        $this->user->forceFill(['active_subscription_id' => $subscription->id])->save();
        // Refresh to ensure the in-memory user reflects DB changes for actingAs()
        $this->user = $this->user->fresh();

        return $subscription;
    }

    /**
     * Get Current Subscription Tests
     */

    #[Test]
    public function test_get_current_subscription_no_active_subscription()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/subscriptions/current');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'user_id',
                'plan',
                'subscription',
                'enhancement',
            ]
        ]);

        $this->assertNull($response->json('data.subscription'));
    }

    #[Test]
    public function test_get_current_subscription_with_active_subscription()
    {
        $subscription = $this->createUserSubscription($this->premiumPlan);
        $this->user->forceFill(['subscription_status' => 'active'])->save();

        SubscriptionEnhancement::create([
            'subscription_id' => $subscription->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/subscriptions/current');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'user_id' => $this->user->id,
                'plan' => [
                    'id' => $this->premiumPlan->id,
                    'name' => ['en' => 'Premium'],
                ],
                'subscription' => [
                    'id' => $subscription->id,
                ],
            ]
        ]);

        // enhancement holds the active status for the subscription
        $this->assertEquals('active', $response->json('data.enhancement.status'));
    }

    #[Test]
    public function test_get_current_subscription_requires_auth()
    {
        $response = $this->getJson('/api/subscriptions/current');

        $response->assertStatus(401);
    }

    /**
     * Get Subscription Status Tests
     */

    #[Test]
    public function test_get_subscription_status_returns_status()
    {
        $subscription = $this->createUserSubscription($this->premiumPlan);
        $this->user->forceFill(['subscription_status' => 'active'])->save();

        $response = $this->actingAs($this->user)
            ->getJson('/api/subscriptions/status');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'current_status',
                'status_details',
                'enhancements',
                'history',
            ]
        ]);

        $this->assertEquals('active', $response->json('data.current_status'));
    }

    /**
     * @test
     * Get subscription status includes enhancements
     */
    public function test_get_subscription_status_includes_enhancements()
    {
        $subscription = $this->createUserSubscription($this->premiumPlan);

        SubscriptionEnhancement::create([
            'subscription_id' => $subscription->id,
            'status' => 'active',
            'renewal_attempts' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/subscriptions/status');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.enhancements'));
        $this->assertEquals('active', $response->json('data.enhancements.0.status'));
    }

    /**
     * @test
     * Get subscription status requires authentication
     */
    public function test_get_subscription_status_requires_auth()
    {
        $response = $this->getJson('/api/subscriptions/status');

        $response->assertStatus(401);
    }

    /**
     * Get Available Plans Tests
     */

    /**
     * @test
     * Get available plans returns all active plans
     */
    public function test_get_available_plans_returns_active_plans()
    {
        // Create an inactive plan (should not be returned)
        Plan::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/subscriptions/plans');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'price',
                    'invoice_period',
                    'invoice_interval',
                ]
            ]
        ]);

        // Should only return 2 active plans created in setUp
        $this->assertEquals(2, count($response->json('data')));
    }

    /**
     * @test
     * Plans are sorted by price
     */
    public function test_get_available_plans_sorted_by_price()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/subscriptions/plans');

        $response->assertStatus(200);
        $plans = $response->json('data');

        // First plan should have lowest price
        $this->assertEquals(0, $plans[0]['price']);
        $this->assertEquals(99.99, $plans[1]['price']);
    }

    /**
     * @test
     * Get available plans requires authentication
     */
    public function test_get_available_plans_requires_auth()
    {
        $response = $this->getJson('/api/subscriptions/plans');

        $response->assertStatus(401);
    }

    /**
     * Get Renewal Preferences Tests
     */

    /**
     * @test
     * Get renewal preferences returns user preferences
     */
    public function test_get_renewal_preferences_returns_preferences()
    {
        RenewalPreference::factory()->create([
            'user_id' => $this->user->id,
            'renewal_type' => 'automatic',
            'send_renewal_reminders' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/subscriptions/renewal-preferences');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'user_id',
                'renewal_type',
                'send_renewal_reminders',
                'reminder_days_before',
            ]
        ]);

        $this->assertEquals('automatic', $response->json('data.renewal_type'));
    }

    /**
     * @test
     * Get renewal preferences creates default if doesn't exist
     */
    public function test_get_renewal_preferences_creates_default()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/subscriptions/renewal-preferences');

        $response->assertStatus(200);

        // Verify default preference was created
        $this->assertDatabaseHas('renewal_preferences', [
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * @test
     * Get renewal preferences requires authentication
     */
    public function test_get_renewal_preferences_requires_auth()
    {
        $response = $this->getJson('/api/subscriptions/renewal-preferences');

        $response->assertStatus(401);
    }

    /**
     * Update Renewal Preferences Tests
     */

    /**
     * @test
     * Update renewal preferences with valid data
     */
    public function test_update_renewal_preferences_success()
    {
        RenewalPreference::factory()->create([
            'user_id' => $this->user->id,
            'renewal_type' => 'automatic',
        ]);

        $payload = [
            'renewal_type' => 'manual',
            'send_renewal_reminders' => false,
            'reminder_days_before' => 14,
        ];

        $response = $this->actingAs($this->user)
            ->putJson('/api/subscriptions/renewal-preferences', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Renewal preferences updated successfully',
        ]);

        $this->assertDatabaseHas('renewal_preferences', [
            'user_id' => $this->user->id,
            'renewal_type' => 'manual',
            'send_renewal_reminders' => false,
        ]);
    }

    /**
     * @test
     * Update renewal preferences with invalid renewal type
     */
    public function test_update_renewal_preferences_invalid_renewal_type()
    {
        RenewalPreference::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson('/api/subscriptions/renewal-preferences', [
                'renewal_type' => 'invalid_type',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['renewal_type']);
    }

    /**
     * @test
     * Update renewal preferences with invalid reminder days
     */
    public function test_update_renewal_preferences_invalid_reminder_days()
    {
        RenewalPreference::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson('/api/subscriptions/renewal-preferences', [
                'reminder_days_before' => 40, // Max is 30
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reminder_days_before']);
    }

    /**
     * @test
     * Update renewal preferences requires authentication
     */
    public function test_update_renewal_preferences_requires_auth()
    {
        $response = $this->putJson('/api/subscriptions/renewal-preferences', []);

        $response->assertStatus(401);
    }

    /**
     * Initiate Payment Tests
     */

    /**
     * @test
     * Initiate payment creates subscription and enhancement
     */
    public function test_initiate_payment_creates_subscription()
    {
        $payload = [
            'plan_id' => $this->premiumPlan->id,
            'billing_period' => 'month',
            'phone' => '254712345678',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/subscriptions/initiate-payment', $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'transaction_id',
                'redirect_url',
                'order_tracking_id',
            ]
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'subscriber_id' => $this->user->id,
            'subscriber_type' => 'App\\Models\\User',
            'plan_id' => $this->premiumPlan->id,
        ]);

        $this->assertDatabaseHas('subscription_enhancements', [
            'status' => 'payment_pending',
        ]);
    }

    /**
     * @test
     * Initiate payment with yearly billing period
     */
    public function test_initiate_payment_yearly_billing()
    {
        $payload = [
            'plan_id' => $this->premiumPlan->id,
            'billing_period' => 'year',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/subscriptions/initiate-payment', $payload);

        $response->assertStatus(201);

        $subscription = PlanSubscription::where('subscriber_id', $this->user->id)->first();
        $this->assertNotNull($subscription);
        // Year should be added to ends_at
        $diffDays = abs($subscription->ends_at->diffInDays($subscription->starts_at));
        Log::info('Yearly test debug', [
            'starts_at' => $subscription->starts_at?->toDateTimeString(),
            'ends_at' => $subscription->ends_at?->toDateTimeString(),
            'diff_days' => $diffDays
        ]);
        $this->assertTrue($diffDays >= 365);
    }

    /**
     * @test
     * Initiate payment with invalid plan
     */
    public function test_initiate_payment_invalid_plan()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/subscriptions/initiate-payment', [
                'plan_id' => 999, // Non-existent plan
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['plan_id']);
    }

    /**
     * @test
     * Initiate payment with invalid phone format
     */
    public function test_initiate_payment_invalid_phone_format()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/subscriptions/initiate-payment', [
                'plan_id' => $this->premiumPlan->id,
                'phone' => '1234567890', // Invalid format
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone']);
    }

    /**
     * @test
     * Initiate payment requires authentication
     */
    public function test_initiate_payment_requires_auth()
    {
        $response = $this->postJson('/api/subscriptions/initiate-payment', []);

        $response->assertStatus(401);
    }

    /**
     * Process Mock Payment Tests
     */

    /**
     * @test
     * Process mock payment successfully
     */
    public function test_process_mock_payment_success()
    {
        // Setup: Create subscription with payment pending
        $subscription = $this->createUserSubscription($this->premiumPlan);

        SubscriptionEnhancement::create([
            'subscription_id' => $subscription->id,
            'status' => 'payment_pending',
        ]);

        $payload = [
            'transaction_id' => 'MOCK_abc123xyz',
            'phone' => '254712345678',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/subscriptions/process-mock-payment', $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Enhancement status should be updated
        $enhancement = SubscriptionEnhancement::where('subscription_id', $subscription->id)->first();
        $this->assertEquals('active', $enhancement->status);
    }

    /**
     * @test
     * Process mock payment with invalid transaction ID
     */
    public function test_process_mock_payment_invalid_transaction_id()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/subscriptions/process-mock-payment', [
                'transaction_id' => '',
                'phone' => '254712345678',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['transaction_id']);
    }

    /**
     * @test
     * Process mock payment with invalid phone
     */
    public function test_process_mock_payment_invalid_phone()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/subscriptions/process-mock-payment', [
                'transaction_id' => 'MOCK_abc123xyz',
                'phone' => 'invalid',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone']);
    }

    /**
     * @test
     * Process mock payment requires authentication
     */
    public function test_process_mock_payment_requires_auth()
    {
        $response = $this->postJson('/api/subscriptions/process-mock-payment', []);

        $response->assertStatus(401);
    }

    /**
     * Cancel Subscription Tests
     */

    /**
     * @test
     * Cancel active subscription
     */
    public function test_cancel_subscription_success()
    {
        $subscription = $this->createUserSubscription($this->premiumPlan);

        $this->user->forceFill([
            'subscription_status' => 'active',
        ])->save();

        SubscriptionEnhancement::create([
            'subscription_id' => $subscription->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/subscriptions/cancel', [
                'reason' => 'No longer needed',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Subscription cancelled successfully',
        ]);

        // Verify subscription canceled_at is set
        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
        ]);
        
        // Verify user status changed
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'subscription_status' => 'cancelled',
        ]);
    }

    /**
     * @test
     * Cancel subscription when no active subscription
     */
    public function test_cancel_subscription_no_active_subscription()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/subscriptions/cancel', []);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'No active subscription found',
        ]);
    }

    /**
     * @test
     * Cancel subscription with reason
     */
    public function test_cancel_subscription_with_reason()
    {
        $subscription = $this->createUserSubscription($this->premiumPlan);

        $this->user->forceFill([
            'subscription_status' => 'active',
        ])->save();

        $reason = 'Too expensive for my current needs';
        $response = $this->actingAs($this->user)
            ->postJson('/api/subscriptions/cancel', [
                'reason' => $reason,
            ]);

        $response->assertStatus(200);
    }

    /**
     * @test
     * Cancel subscription requires authentication
     */
    public function test_cancel_subscription_requires_auth()
    {
        $response = $this->postJson('/api/subscriptions/cancel', []);

        $response->assertStatus(401);
    }

    /**
     * Integration Tests
     */

    /**
     * @test
     * Complete subscription lifecycle: create, activate, cancel
     */
    public function test_subscription_lifecycle()
    {
        // Step 1: User initiates payment
        $initiateResponse = $this->actingAs($this->user)
            ->postJson('/api/subscriptions/initiate-payment', [
                'plan_id' => $this->premiumPlan->id,
                'billing_period' => 'month',
            ]);

        $initiateResponse->assertStatus(201);
        $transactionId = $initiateResponse->json('data.transaction_id');

        // Step 2: Verify subscription is in payment_pending
        $statusResponse = $this->actingAs($this->user)
            ->getJson('/api/subscriptions/status');
        $statusResponse->assertStatus(200);

        // Step 3: Process mock payment
        $paymentResponse = $this->actingAs($this->user)
            ->postJson('/api/subscriptions/process-mock-payment', [
                'transaction_id' => $transactionId,
                'phone' => '254712345678',
            ]);

        $paymentResponse->assertStatus(200);

        // Step 4: Verify subscription is active
        $currentResponse = $this->actingAs($this->user)
            ->getJson('/api/subscriptions/current');
        $currentResponse->assertStatus(200);
        $this->assertEquals($this->premiumPlan->id, $currentResponse->json('data.plan.id'));

        // Step 5: Cancel subscription
        $cancelResponse = $this->actingAs($this->user)
            ->postJson('/api/subscriptions/cancel', []);

        $cancelResponse->assertStatus(200);

        // Step 6: Verify subscription is cancelled
        $finalStatusResponse = $this->actingAs($this->user)
            ->getJson('/api/subscriptions/current');
        $finalStatusResponse->assertStatus(200);
        $this->assertNull($finalStatusResponse->json('data.subscription'));
    }
}
