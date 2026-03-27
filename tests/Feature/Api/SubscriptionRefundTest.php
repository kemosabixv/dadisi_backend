<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);

        // Ensure the permission exists for the api guard
        if (!\Spatie\Permission\Models\Permission::where('name', 'manage_refunds')->exists()) {
            \Spatie\Permission\Models\Permission::create(['name' => 'manage_refunds', 'guard_name' => 'web']);
        }

        // Create an admin who should receive notifications
        $admin = User::factory()->create();
        $admin->givePermissionTo('manage_refunds');
    }

    public function test_user_can_request_refund_after_cancelling_subscription(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        
        // Following user feedback: cancellation > refund request
        $subscription = PlanSubscription::factory()->create([
            'subscriber_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'cancelled',
            'canceled_at' => now(),
        ]);

        $payment = Payment::factory()->create([
            'payable_type' => 'subscription', // Correct key from morphMap
            'payable_id' => $subscription->id,
            'status' => 'paid',
            'amount' => 5000,
            'transaction_id' => 'TRANS-SUB-888',
            'paid_at' => now()->subDays(2),
        ]);

        $this->actingAs($user);
        $response = $this->postJson('/api/refunds', [
            'order_reference' => 'TRANS-SUB-888',
            'email' => $user->email,
            'reason' => 'cancellation',
            'customer_notes' => 'I want to cancel my subscription.',
        ]);

        // RefundController returns 200 for successful submission
        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('refunds', [
            'refundable_type' => 'subscription',
            'refundable_id' => $subscription->id,
            'status' => Refund::STATUS_PENDING,
        ]);
        
        // Verify notification was sent (to admin)
        Notification::assertSentTo(
            User::permission('manage_refunds')->get(),
            \App\Notifications\RefundRequestSubmitted::class
        );
    }

    public function test_completing_subscription_refund_cancels_subscription(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        
        $subscription = PlanSubscription::factory()->create([
            'subscriber_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $refund = Refund::factory()->create([
            'refundable_type' => 'subscription',
            'refundable_id' => $subscription->id,
            'status' => Refund::STATUS_APPROVED,
            'amount' => 5000,
        ]);

        // Trigger completion logic via Service
        $refundService = new \App\Services\RefundService(
            app(\App\Services\PaymentGateway\GatewayManager::class),
            app(\App\Services\Contracts\NotificationServiceContract::class)
        );
        
        $reflection = new \ReflectionClass($refundService);
        $method = $reflection->getMethod('completeRefund');
        $method->setAccessible(true);
        $method->invokeArgs($refundService, [$refund]);

        $this->assertEquals('cancelled', $subscription->fresh()->status);
        $this->assertNotNull($subscription->fresh()->canceled_at);
    }
}
