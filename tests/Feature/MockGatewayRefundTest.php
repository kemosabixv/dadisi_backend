<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Contracts\PaymentServiceContract;
use App\Services\Contracts\RefundServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MockGatewayRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure we are using mock gateway
        config(['payment.gateway' => 'mock']);
    }

    #[Test]
    public function it_captures_and_persists_confirmation_code_from_mock_gateway()
    {
        $user = User::factory()->create();
        $paymentService = app(PaymentServiceContract::class);

        // 1. Initiate a payment
        $paymentData = [
            'amount' => 1000,
            'currency' => 'KES',
            'payment_method' => 'pesapal',
            'description' => 'Test Payment',
            'payable_type' => 'test',
            'payable_id' => 1,
        ];

        $initiation = $paymentService->processPayment($user, $paymentData);
        $transactionId = $initiation['transaction_id'];

        $payment = Payment::where('transaction_id', $transactionId)->first();
        $this->assertNull($payment->confirmation_code);

        // Mark as paid so that verification triggers confirmation code capture
        $payment->update(['status' => 'paid']);

        // 2. Verify payment (this should trigger status query and capture confirmation_code)
        // We simulate the flow where verifyPayment is called with the transaction ID
        $paymentService->verifyPayment($user, $transactionId);
        
        $payment->refresh();
        $this->assertNotNull($payment->confirmation_code);
        $this->assertStringStartsWith('MOCK_CONF_', $payment->confirmation_code);
    }

    #[Test]
    public function it_uses_persisted_confirmation_code_for_refunds()
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        \Spatie\Permission\Models\Permission::findOrCreate('manage_refunds', 'web');
        $admin->givePermissionTo('manage_refunds');
        
        $paymentService = app(PaymentServiceContract::class);
        $refundService = app(RefundServiceContract::class);

        // 1. Create a paid payment with a confirmation code
        $order = \App\Models\EventOrder::factory()->create([
            'user_id' => $user->id,
            'status' => 'paid',
        ]);
        
        $payment = Payment::create([
            'payer_id' => $user->id,
            'amount' => 1500,
            'currency' => 'KES',
            'gateway' => 'mock',
            'status' => 'paid',
            'transaction_id' => 'TXN_' . uniqid(),
            'confirmation_code' => 'MOCK_CONF_REF_TEST',
            'order_reference' => 'ORD_' . uniqid(),
            'payable_type' => 'event_order',
            'payable_id' => $order->id,
        ]);

        // 2. Create a refund request
        $refundRequest = $refundService->submitRefundRequest(
            'EventOrder',
            $order->id,
            'Reason for refund',
            'Notes'
        );

        // 3. Approve and process the refund
        $refundRequest = $refundService->approveRefund($refundRequest, $admin);
        
        // Mock environment auto-completes the refund, so we check if it went through
        $processedRefund = $refundService->processRefund($refundRequest);

        $this->assertEquals('completed', $processedRefund->status);
        $payment->refresh();
        $this->assertEquals('refunded', $payment->status);
    }
}
