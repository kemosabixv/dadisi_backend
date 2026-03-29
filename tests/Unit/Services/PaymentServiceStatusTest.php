<?php

namespace Tests\Unit\Services;

use App\Models\Payment;
use App\Models\PlanSubscription;
use App\Models\User;
use App\Models\Plan;
use App\Services\Payments\PaymentService;
use App\Services\PaymentGateway\GatewayManager;
use App\DTOs\Payments\PaymentStatusDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceStatusTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;
    private $gatewayManager;
    private $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->gatewayManager = $this->createMock(\App\Services\PaymentGateway\GatewayManager::class);
        $this->notificationService = $this->createMock(\App\Services\Contracts\NotificationServiceContract::class);
        
        $this->service = new PaymentService(
            $this->gatewayManager,
            $this->notificationService
        );
    }

    public function test_verify_payment_updates_confirmation_code_from_dto(): void
    {
        $user = User::factory()->create();
        $payment = Payment::create([
            'transaction_id' => 'TRANS-123',
            'status' => 'pending',
            'amount' => 1000,
            'currency' => 'KES',
            'gateway' => 'mock',
            'payer_id' => $user->id,
            'order_reference' => 'ORD-123',
            'payable_type' => 'test',
            'payable_id' => 1,
        ]);

        $dto = new PaymentStatusDTO(
            transactionId: 'TRANS-123',
            merchantReference: 'ORD-123',
            status: 'COMPLETED',
            amount: 1000,
            confirmationCode: 'MOCK-CONF-ABC',
            rawDetails: ['payment_method' => 'MPESA']
        );

        // Map GatewayManager to return our DTO
        $this->gatewayManager->method('queryStatus')->willReturn($dto);
        
        $this->service->verifyPayment($payment);

        $payment->refresh();
        $this->assertEquals('paid', $payment->status);
        $this->assertEquals('MOCK-CONF-ABC', $payment->confirmation_code);
    }

    public function test_handle_recurring_payment_saves_confirmation_code(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['invoice_period' => 'month', 'invoice_interval' => 1]);
        $subscription = PlanSubscription::create([
            'subscriber_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $dto = new PaymentStatusDTO(
            transactionId: 'RECURRING-123',
            merchantReference: 'SUB-REF-123',
            status: 'COMPLETED',
            amount: 500,
            confirmationCode: 'MPESA-RECUR-999',
            rawDetails: ['currency' => 'KES']
        );

        $this->service->handleRecurringPayment($subscription, 'RECURRING-123', $dto, $dto->rawDetails, 'MPESA');

        $this->assertDatabaseHas('payments', [
            'transaction_id' => 'RECURRING-123',
            'confirmation_code' => 'MPESA-RECUR-999',
            'payable_id' => $subscription->id,
            'status' => 'paid',
        ]);
    }
}
