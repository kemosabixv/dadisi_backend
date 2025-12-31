<?php

namespace Tests\Feature\Services\Payments;

use App\DTOs\Payments\PaymentRequestDTO;
use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\PaymentGateway\GatewayManager;
use App\Services\PaymentGateway\MockGatewayAdapter;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PaymentServiceTest
 *
 * Test suite for the refactored DTO-based PaymentService.
 * Tests cover:
 * - Payment initiation via processPayment
 * - Payment verification
 * - Payment history retrieval
 * - Refund operations
 * - Payment filtering
 */
class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;
    private User $payer;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use mock gateway for tests
        config(['payment.gateway' => 'mock']);
        
        $this->service = app(PaymentService::class);
        $this->payer = User::factory()->create();
        $this->admin = User::factory()->create();
    }

    // ============ Payment Form Metadata Tests ============

    #[Test]
    /**
     * Can get payment form metadata
     */
    public function it_returns_payment_form_metadata(): void
    {
        $metadata = $this->service->getPaymentFormMetadata();

        $this->assertArrayHasKey('methods', $metadata);
        $this->assertArrayHasKey('currencies', $metadata);
        $this->assertArrayHasKey('billing_periods', $metadata);
        $this->assertArrayHasKey('active_gateway', $metadata);
        $this->assertContains('KES', $metadata['currencies']);
    }

    // ============ Payment Processing Tests ============

    #[Test]
    /**
     * Can initiate a payment with valid data
     */
    public function it_can_initiate_payment_with_valid_data(): void
    {
        $data = [
            'amount' => 5000,
            'currency' => 'KES',
            'payment_method' => 'mpesa',
            'description' => 'Test payment',
            'email' => 'test@example.com',
            'phone' => '254712345678',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];

        $result = $this->service->processPayment($this->payer, $data);

        $this->assertArrayHasKey('payment_id', $result);
        $this->assertArrayHasKey('transaction_id', $result);
        $this->assertArrayHasKey('redirect_url', $result);
        $this->assertEquals('pending', $result['status']);
    }

    #[Test]
    /**
     * Creates payment record in database
     */
    public function it_creates_payment_record_in_database(): void
    {
        $data = [
            'amount' => 2500,
            'payment_method' => 'mpesa',
            'description' => 'Test subscription',
        ];

        $result = $this->service->processPayment($this->payer, $data);

        $this->assertDatabaseHas('payments', [
            'id' => $result['payment_id'],
            'payer_id' => $this->payer->id,
            'amount' => 2500,
            'status' => 'pending',
        ]);
    }

    #[Test]
    /**
     * Sets default currency to KES
     */
    public function it_defaults_currency_to_kes(): void
    {
        $data = [
            'amount' => 1000,
            'payment_method' => 'card',
        ];

        $result = $this->service->processPayment($this->payer, $data);

        $payment = Payment::find($result['payment_id']);
        $this->assertEquals('KES', $payment->currency);
    }

    // ============ Payment Verification Tests ============

    #[Test]
    /**
     * Can verify a payment by transaction ID
     */
    public function it_can_verify_payment_by_transaction_id(): void
    {
        // Create a payment first
        $payment = Payment::factory()->create([
            'payer_id' => $this->payer->id,
            'status' => 'pending',
            'transaction_id' => 'TEST_TXN_123',
        ]);

        $result = $this->service->verifyPayment($this->payer, 'TEST_TXN_123');

        $this->assertArrayHasKey('transaction_id', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('is_paid', $result);
    }

    #[Test]
    /**
     * Throws exception for non-existent transaction
     */
    public function it_throws_exception_for_non_existent_transaction(): void
    {
        $this->expectException(PaymentException::class);
        $this->service->verifyPayment($this->payer, 'NON_EXISTENT_TXN');
    }

    // ============ Check Payment Status Tests ============

    #[Test]
    /**
     * Can check payment status by transaction ID
     */
    public function it_can_check_payment_status(): void
    {
        $payment = Payment::factory()->create([
            'transaction_id' => 'CHECK_TXN_456',
            'status' => 'pending',
            'amount' => 3000,
        ]);

        $status = $this->service->checkPaymentStatus('CHECK_TXN_456');

        $this->assertEquals('CHECK_TXN_456', $status['transaction_id']);
        $this->assertEquals('pending', $status['status']);
        $this->assertEquals(3000, $status['amount']);
    }

    #[Test]
    /**
     * Throws exception for not found payment
     */
    public function it_throws_exception_for_not_found_payment(): void
    {
        $this->expectException(PaymentException::class);
        $this->service->checkPaymentStatus('INVALID_TXN');
    }

    // ============ Payment History Tests ============

    #[Test]
    /**
     * Can get payment history for user
     */
    public function it_can_get_payment_history_for_user(): void
    {
        Payment::factory(5)->create(['payer_id' => $this->payer->id]);
        Payment::factory(3)->create(); // Other user's payments

        $history = $this->service->getPaymentHistory($this->payer);

        $this->assertEquals(5, $history->total());
    }

    #[Test]
    /**
     * Payment history respects pagination
     */
    public function it_respects_pagination_in_payment_history(): void
    {
        Payment::factory(20)->create(['payer_id' => $this->payer->id]);

        $history = $this->service->getPaymentHistory($this->payer, 10);

        $this->assertEquals(10, $history->count());
        $this->assertTrue($history->hasMorePages());
    }

    // ============ List Payments Tests ============

    #[Test]
    /**
     * Can list payments with filters
     */
    public function it_can_list_payments_with_filters(): void
    {
        Payment::factory(5)->create(['status' => 'paid']);
        Payment::factory(5)->create(['status' => 'pending']);

        $payments = $this->service->listPayments(['status' => 'paid']);

        $this->assertEquals(5, $payments->total());
    }

    #[Test]
    /**
     * Can filter payments by amount range
     */
    public function it_can_filter_payments_by_amount_range(): void
    {
        Payment::factory(5)->create(['amount' => 1000]);
        Payment::factory(5)->create(['amount' => 10000]);

        $payments = $this->service->listPayments([
            'amount_from' => 5000,
            'amount_to' => 15000,
        ]);

        $this->assertEquals(5, $payments->total());
    }

    #[Test]
    /**
     * Can filter payments by date range
     */
    public function it_can_filter_payments_by_date_range(): void
    {
        Payment::factory(5)->create(['created_at' => now()->subDays(10)]);
        Payment::factory(3)->create(['created_at' => now()]);

        $payments = $this->service->listPayments([
            'date_from' => now()->subDays(2)->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $this->assertEquals(3, $payments->total());
    }

    // ============ Refund Tests ============

    #[Test]
    /**
     * Can refund a paid payment
     */
    public function it_can_refund_paid_payment(): void
    {
        $payment = Payment::factory()->create([
            'status' => 'paid',
            'amount' => 5000,
            'transaction_id' => 'TRANS-123',
        ]);

        $reason = 'Customer request';

        // Mock gateway success
        $mockResult = \App\DTOs\Payments\TransactionResultDTO::success(
            transactionId: 'REF-999',
            merchantReference: 'TRANS-123',
            status: 'REFUNDED'
        );

        $mock = \Mockery::mock(\App\Services\PaymentGateway\GatewayManager::class)->makePartial();
        $mock->shouldReceive('refund')->once()->andReturn($mockResult);

        // Inject mock
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('gatewayManager');
        $property->setAccessible(true);
        $property->setValue($this->service, $mock);

        $result = $this->service->refundPayment($this->admin, [
            'transaction_id' => $payment->transaction_id,
            'reason' => $reason,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('refunded', $result['status']);
        
        $payment->refresh();
        $this->assertEquals('refunded', $payment->status);
        $this->assertEquals('REF-999', $payment->metadata['refund_transaction_id']);
    }

    #[Test]
    /**
     * Throws exception when refunding non-paid payment
     */
    public function it_throws_exception_for_non_paid_refund(): void
    {
        $payment = Payment::factory()->create(['status' => 'pending']);

        $this->expectException(PaymentException::class);
        $this->service->refundPayment($this->admin, [
            'payment_id' => $payment->id,
            'reason' => 'Test',
        ]);
    }

    #[Test]
    /**
     * Refund updates payment status to refunded
     */
    public function it_updates_payment_status_on_refund(): void
    {
        $payment = Payment::factory()->create(['status' => 'paid']);

        $this->service->refundPayment($this->admin, [
            'transaction_id' => $payment->id, // paymentId is also supported as a fallback
            'reason' => 'Duplicate payment',
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'refunded',
        ]);
    }

    // ============ Webhook Handling Tests ============

    #[Test]
    /**
     * Can handle webhook with OrderTrackingId
     */
    public function it_can_handle_webhook_with_order_tracking_id(): void
    {
        $payment = Payment::factory()->create([
            'transaction_id' => 'WEBHOOK_TXN_789',
            'status' => 'pending',
        ]);

        $result = $this->service->handleWebhook([
            'OrderTrackingId' => 'WEBHOOK_TXN_789',
        ]);

        $this->assertArrayHasKey('transaction_id', $result);
    }

    #[Test]
    /**
     * Webhook returns error for missing transaction reference
     */
    public function it_returns_error_for_missing_webhook_reference(): void
    {
        $result = $this->service->handleWebhook([]);

        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('No transaction reference', $result['message']);
    }

    // ============ Edge Cases ============

    #[Test]
    /**
     * Handles empty filter list
     */
    public function it_handles_empty_filters(): void
    {
        Payment::factory(10)->create();

        $payments = $this->service->listPayments([]);

        $this->assertEquals(10, $payments->total());
    }

    #[Test]
    /**
     * Returns empty results for non-matching filters
     */
    public function it_returns_empty_for_non_matching_filters(): void
    {
        Payment::factory(10)->create(['status' => 'paid']);

        $payments = $this->service->listPayments(['status' => 'cancelled']);

        $this->assertEquals(0, $payments->total());
    }
    
    // ============ Recurring Payment Tests ============
    #[Test]
    /**
     * Can handle RECURRING webhook and extend subscription
     */
    public function it_handles_recurring_payment_webhook_and_extends_subscription(): void
    {
        // 1. Setup: Create a subscriber and an active subscription
        $plan = \App\Models\Plan::factory()->create([
            'invoice_period' => 'month',
            'invoice_interval' => 1,
            'price' => 2000,
            'name' => ['en' => 'Monthly Plan'],
            'slug' => 'monthly',
        ]);
        
        $subscription = \App\Models\PlanSubscription::create([
            'subscriber_id' => $this->payer->id,
            'subscriber_type' => 'App\Models\User',
            'plan_id' => $plan->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addDays(2), // Ending soon
            'status' => 'active',
            'name' => 'Monthly Plan',
            'slug' => 'monthly-plan-' . uniqid(),
        ]);
        
        \App\Models\SubscriptionEnhancement::create([
            'subscription_id' => $subscription->id,
            'status' => 'active',
        ]);
        
        $originalEndsAt = $subscription->ends_at;

        // 2. Mock Gateway queryStatus response for a RECURRING notification
        // The account_number format is expected to be USER-{userId}-PLAN-{planId}
        $accountNumber = "USER-{$this->payer->id}-PLAN-{$plan->id}";
        
        $mockStatus = new \App\DTOs\Payments\PaymentStatusDTO(
            transactionId: 'REC_TXN_001',
            merchantReference: 'SUB_' . $subscription->id,
            status: 'COMPLETED',
            amount: 2000,
            currency: 'KES',
            paymentMethod: 'Card',
            paidAt: now()->toDateTimeString(),
            rawDetails: [
                'account_number' => $accountNumber,
                'payment_method' => 'Card',
                'amount' => 2000,
            ]
        );

        // Mock the GatewayManager to return this status
        $mock = \Mockery::mock(\App\Services\PaymentGateway\GatewayManager::class)->makePartial();
        $mock->shouldReceive('queryStatus')->with('REC_TXN_001')->andReturn($mockStatus);
        
        // Temporarily replace the gateway manager in the service
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('gatewayManager');
        $property->setAccessible(true);
        $property->setValue($this->service, $mock);

        // 3. Process the webhook
        $result = $this->service->handleWebhook([
            'OrderTrackingId' => 'REC_TXN_001',
            'OrderNotificationType' => 'RECURRING',
        ]);

        // 4. Verify results
        $this->assertEquals('recurring_processed', $result['status']);
        
        // Check if subscription was extended
        $subscription->refresh();
        $this->assertTrue($subscription->ends_at->isAfter($originalEndsAt));
        $this->assertEquals($originalEndsAt->addMonth()->toDateTimeString(), $subscription->ends_at->toDateTimeString());
        
        // Check if a new payment record was created
        $this->assertDatabaseHas('payments', [
            'transaction_id' => 'REC_TXN_001',
            'payer_id' => $this->payer->id,
            'payable_id' => $subscription->id,
            'status' => 'paid',
        ]);
    }

    #[Test]
    /**
     * Does not double-process recurring payments
     */
    public function it_does_not_double_process_recurring_payments(): void
    {
        // Setup subscription
        $plan = \App\Models\Plan::factory()->create(['invoice_period' => 'month', 'invoice_interval' => 1]);
        $subscription = \App\Models\PlanSubscription::create([
            'subscriber_id' => $this->payer->id,
            'subscriber_type' => 'App\Models\User',
            'plan_id' => $plan->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addDays(2),
            'status' => 'active',
            'name' => 'Monthly Plan',
            'slug' => 'monthly-plan-' . uniqid(),
        ]);
        
        // Create an existing payment for this transaction
        Payment::factory()->create([
            'transaction_id' => 'REC_TXN_DUP',
            'status' => 'paid',
            'payer_id' => $this->payer->id,
        ]);
        
        $originalEndsAt = $subscription->ends_at;

        // Mock gateway
        $mockStatus = new \App\DTOs\Payments\PaymentStatusDTO(
            transactionId: 'REC_TXN_DUP',
            merchantReference: 'SUB_' . $subscription->id,
            status: 'COMPLETED',
            amount: 2000,
            rawDetails: ['account_number' => "USER-{$this->payer->id}-PLAN-{$plan->id}", 'payment_method' => 'Card']
        );

        $mock = \Mockery::mock(\App\Services\PaymentGateway\GatewayManager::class)->makePartial();
        $mock->shouldReceive('queryStatus')->andReturn($mockStatus);
        
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('gatewayManager');
        $property->setAccessible(true);
        $property->setValue($this->service, $mock);

        // Process webhook twice
        $this->service->handleWebhook(['OrderTrackingId' => 'REC_TXN_DUP', 'OrderNotificationType' => 'RECURRING']);
        $result = $this->service->handleWebhook(['OrderTrackingId' => 'REC_TXN_DUP', 'OrderNotificationType' => 'RECURRING']);

        $this->assertEquals('paid', $result['status']);
        $this->assertEquals('Already processed', $result['message']);
        
        // Ensure ends_at DID NOT change (since it was already "processed" by our manual factory creation above)
        $subscription->refresh();
        $this->assertEquals($originalEndsAt->toDateTimeString(), $subscription->ends_at->toDateTimeString());
    }

    #[Test]
    /**
     * Handles failed subscription resolution
     */
    public function it_handles_failed_subscription_resolution_for_recurring_payment(): void
    {
        $mockStatus = new \App\DTOs\Payments\PaymentStatusDTO(
            transactionId: 'REC_TXN_FAIL',
            merchantReference: 'INVALID_REF',
            status: 'COMPLETED',
            amount: 2000,
            rawDetails: ['account_number' => "INVALID"]
        );

        $mock = \Mockery::mock(\App\Services\PaymentGateway\GatewayManager::class)->makePartial();
        $mock->shouldReceive('queryStatus')->andReturn($mockStatus);
        
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('gatewayManager');
        $property->setAccessible(true);
        $property->setValue($this->service, $mock);

        $result = $this->service->handleWebhook([
            'OrderTrackingId' => 'REC_TXN_FAIL',
            'OrderNotificationType' => 'RECURRING',
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Subscription resolution failed', $result['message']);
    }

    #[Test]
    /**
     * Does not extend subscription if recurring payment is not paid
     */
    public function it_does_not_extend_subscription_if_recurring_payment_is_not_paid(): void
    {
        // Setup subscription
        $plan = \App\Models\Plan::factory()->create(['invoice_period' => 'month', 'invoice_interval' => 1]);
        $subscription = \App\Models\PlanSubscription::create([
            'subscriber_id' => $this->payer->id,
            'subscriber_type' => 'App\Models\User',
            'plan_id' => $plan->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addDays(2),
            'status' => 'active',
            'name' => 'Monthly Plan',
            'slug' => 'monthly-plan-' . uniqid(),
        ]);
        
        $originalEndsAt = $subscription->ends_at;

        // Mock gateway with PENDING status
        $mockStatus = new \App\DTOs\Payments\PaymentStatusDTO(
            transactionId: 'REC_TXN_PENDING',
            merchantReference: 'SUB_' . $subscription->id,
            status: 'PENDING',
            amount: 2000,
            rawDetails: ['account_number' => "USER-{$this->payer->id}-PLAN-{$plan->id}"]
        );

        $mock = \Mockery::mock(\App\Services\PaymentGateway\GatewayManager::class)->makePartial();
        $mock->shouldReceive('queryStatus')->andReturn($mockStatus);
        
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('gatewayManager');
        $property->setAccessible(true);
        $property->setValue($this->service, $mock);

        $result = $this->service->handleWebhook([
            'OrderTrackingId' => 'REC_TXN_PENDING',
            'OrderNotificationType' => 'RECURRING',
        ]);

        $this->assertFalse($result['is_paid']);
        
        // Ensure ends_at DID NOT change
        $subscription->refresh();
        $this->assertEquals($originalEndsAt->toDateTimeString(), $subscription->ends_at->toDateTimeString());
    }
}
