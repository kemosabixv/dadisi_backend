<?php

namespace Tests\Unit\Services;

use App\DTOs\Payments\TransactionResultDTO;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentGateway\PaymentGatewayInterface;
use App\Services\PaymentGateway\GatewayManager;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;


class PaymentRefundTest extends TestCase
{
    use RefreshDatabase;

    protected $paymentService;

    protected $gatewayManager;

    protected $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gatewayManager = Mockery::mock(GatewayManager::class);
        $this->gateway = Mockery::mock(PaymentGatewayInterface::class);

        $this->paymentService = new PaymentService($this->gatewayManager);
    }


    #[Test]
    public function it_can_refund_a_payment_successfully()
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->create([
            'status' => 'paid',
            'transaction_id' => 'TRANS_123',
            'gateway' => 'pesapal',
            'amount' => 100.00,
        ]);

        // Mock the refund method directly on gatewayManager
        $this->gatewayManager->shouldReceive('refund')
            ->once()
            ->with('TRANS_123', 100.00, 'Customer request')
            ->andReturn(new TransactionResultDTO(
                success: true,
                transactionId: 'REF_123',
                status: 'refunded',
                message: 'Refund successful'
            ));

        $result = $this->paymentService->refundPayment($user, [
            'transaction_id' => 'TRANS_123',
            'reason' => 'Customer request',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('refunded', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->refunded_at);
    }


    #[Test]
    public function it_fails_if_payment_already_refunded()
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->create([
            'status' => 'refunded',
            'transaction_id' => 'TRANS_123',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only paid payments can be refunded');

        $this->paymentService->refundPayment($user, [
            'transaction_id' => 'TRANS_123',
            'reason' => 'Customer request',
        ]);
    }
}

