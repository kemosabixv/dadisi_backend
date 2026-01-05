<?php

namespace Tests\Feature\Notifications;

use App\Models\Donation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\PlanSubscription;
use App\Models\StudentApprovalRequest;
use App\Models\User;
use App\Models\Plan;
use App\Models\County;
use App\Models\Refund;
use App\Notifications\DonationReceived;
use App\Notifications\EventRegistrationConfirmation;
use App\Notifications\RefundProcessed;
use App\Notifications\StudentApprovalSubmitted;
use App\Notifications\SubscriptionActivated;
use App\Services\Events\EventRegistrationService;
use App\Services\Payments\PaymentService;
use App\Services\RefundService;
use App\Services\StudentApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class NotificationRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $shouldSeedRoles = true;

    /**
     * Test EventRegistrationConfirmation is routed to the correct user.
     */
    public function test_event_registration_confirmation_is_routed_to_user()
    {
        Notification::fake();

        $user = User::factory()->create();
        $county = County::factory()->create();
        $event = Event::factory()->create([
            'capacity' => 10,
            'county_id' => $county->id
        ]);

        $service = app(EventRegistrationService::class);
        $registration = $service->registerUser($user, $event);

        Notification::assertSentTo(
            $user,
            EventRegistrationConfirmation::class
        );
    }

    /**
     * Test DonationReceived is routed to the donor on payment verification.
     */
    public function test_donation_received_is_routed_to_donor()
    {
        Notification::fake();

        $user = User::factory()->create();
        $county = County::factory()->create();
        $donation = Donation::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'amount' => 1000,
            'county_id' => $county->id,
        ]);

        $payment = new Payment();
        $payment->payable_id = $donation->id;
        $payment->payable_type = Donation::class;
        $payment->payer_id = $user->id;
        $payment->amount = 1000;
        $payment->currency = 'KES';
        $payment->status = 'pending';
        $payment->transaction_id = 'TEST_TX_123';
        $payment->order_reference = 'ORDER_DON_123';
        $payment->gateway = 'mock';
        $payment->save();

        // Mock gateway
        $mockResult = new \App\DTOs\Payments\PaymentStatusDTO('TEST_TX_123', 'ORDER_DON_123', 'COMPLETED', 1000, 'KES');
        $this->mock(\App\Services\PaymentGateway\GatewayManager::class, function ($mock) use ($mockResult) {
            $mock->shouldReceive('queryStatus')->andReturn($mockResult);
        });

        $service = app(PaymentService::class);
        $service->verifyPayment($user, 'TEST_TX_123');

        Notification::assertSentTo(
            $user,
            DonationReceived::class
        );
    }

    /**
     * Test SubscriptionActivated is routed to the subscriber on payment verification.
     */
    public function test_subscription_activated_is_routed_to_subscriber()
    {
        Notification::fake();

        $user = User::factory()->create();
        
        $plan = Plan::create([
            'name' => 'Premium Plan',
            'slug' => 'premium',
            'description' => 'Test plan',
            'price' => 500,
            'currency' => 'KES',
            'type' => 'monthly',
            'is_active' => true,
        ]);

        $subscription = PlanSubscription::create([
            'subscriber_id' => $user->id,
            'subscriber_type' => User::class,
            'plan_id' => $plan->id,
            'name' => 'Premium Plan',
            'slug' => 'premium',
            'status' => 'pending'
        ]);

        $payment = new Payment();
        $payment->payable_id = $subscription->id;
        $payment->payable_type = PlanSubscription::class;
        $payment->payer_id = $user->id;
        $payment->amount = 500;
        $payment->currency = 'KES';
        $payment->status = 'pending';
        $payment->transaction_id = 'SUB_TX_123';
        $payment->order_reference = 'ORDER_SUB_123';
        $payment->gateway = 'mock';
        $payment->save();

        // Mock gateway
        $mockResult = new \App\DTOs\Payments\PaymentStatusDTO('SUB_TX_123', 'ORDER_SUB_123', 'COMPLETED', 500, 'KES');
        $this->mock(\App\Services\PaymentGateway\GatewayManager::class, function ($mock) use ($mockResult) {
            $mock->shouldReceive('queryStatus')->andReturn($mockResult);
        });

        $service = app(PaymentService::class);
        $service->verifyPayment($user, 'SUB_TX_123');

        Notification::assertSentTo(
            $user,
            SubscriptionActivated::class
        );
    }

    /**
     * Test RefundProcessed is routed to the payer.
     */
    public function test_refund_processed_is_routed_to_payer()
    {
        Notification::fake();

        $user = User::factory()->create();
        $payment = Payment::factory()->create([
            'payer_id' => $user->id,
            'status' => 'paid',
            'amount' => 1000,
            'transaction_id' => 'TX_TO_REFUND',
            'order_reference' => 'ORDER_REFUND_123',
            'gateway' => 'mock'
        ]);

        $refund = Refund::create([
            'payment_id' => $payment->id,
            'refundable_type' => User::class,
            'refundable_id' => $user->id,
            'amount' => 1000,
            'original_amount' => 1000,
            'status' => 'approved',
            'reason' => 'Test',
            'requested_at' => now(),
            'currency' => 'KES',
            'gateway' => 'mock'
        ]);

        // Mock gateway for refund
        $mockResult = \App\DTOs\Payments\TransactionResultDTO::success('REFUND_ID_999', 'TX_TO_REFUND', 'REFUNDED', 'Success');
        $this->mock(\App\Services\PaymentGateway\GatewayManager::class, function ($mock) use ($mockResult) {
            $mock->shouldReceive('refund')->andReturn($mockResult);
        });

        $refundService = app(RefundService::class);
        $refundService->processRefund($refund);

        Notification::assertSentTo(
            $user,
            RefundProcessed::class
        );
    }

    /**
     * Test StudentApprovalSubmitted is routed to staff with permission.
     */
    public function test_student_approval_submitted_is_routed_to_relevant_staff()
    {
        Notification::fake();

        $student = User::factory()->create();
        
        // Create staff with permission - explicitly use guards since seeder does
        $staff = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'approve_student_approvals', 'guard_name' => 'web']);
        $staff->givePermissionTo($permission);

        // Create random user without permission
        $otherUser = User::factory()->create();

        $service = app(StudentApprovalService::class);
        $service->submitApprovalRequest($student->id, [
            'student_institution' => 'Dadisi Academy',
            'student_email' => 'student@test.com',
            'documentation_url' => 'https://dadisi.org/docs/123',
            'birth_date' => '2000-01-01',
            'county' => 'Nairobi'
        ]);

        // Should be sent to staff
        Notification::assertSentTo(
            $staff,
            StudentApprovalSubmitted::class
        );

        // Should NOT be sent to other random user
        Notification::assertNotSentTo(
            $otherUser,
            StudentApprovalSubmitted::class
        );
    }

    /**
     * Test DonationReceived is routed to guest email on verification.
     */
    public function test_guest_donation_received_is_routed_to_email()
    {
        Notification::fake();

        $county = County::factory()->create();
        $donation = Donation::factory()->create([
            'user_id' => null,
            'donor_email' => 'guest@example.com',
            'status' => 'pending',
            'amount' => 1000,
            'county_id' => $county->id,
        ]);

        $payment = Payment::create([
            'payable_id' => $donation->id,
            'payable_type' => Donation::class,
            'payer_id' => null,
            'amount' => 1000,
            'currency' => 'KES',
            'status' => 'pending',
            'transaction_id' => 'GUEST_TX_123',
            'order_reference' => 'GUEST_ORDER_123',
            'gateway' => 'mock'
        ]);

        // Mock gateway
        $mockResult = new \App\DTOs\Payments\PaymentStatusDTO('GUEST_TX_123', 'GUEST_ORDER_123', 'COMPLETED', 1000, 'KES');
        $this->mock(\App\Services\PaymentGateway\GatewayManager::class, function ($mock) use ($mockResult) {
            $mock->shouldReceive('queryStatus')->andReturn($mockResult);
        });

        // Use any user as actor for verification
        $actor = User::factory()->create();
        $service = app(PaymentService::class);
        $service->verifyPayment($actor, 'GUEST_TX_123');

        Notification::assertSentOnDemand(
            DonationReceived::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'guest@example.com';
            }
        );
    }
}
