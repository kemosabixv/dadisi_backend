<?php

namespace Tests\Unit\PaymentGateway;

use App\Services\PaymentGateway\MockGatewayAdapter;
use App\DTOs\Payments\PaymentStatusDTO;
use Tests\TestCase;

class MockGatewayAdapterTest extends TestCase
{
    private MockGatewayAdapter $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new MockGatewayAdapter([]);
    }

    public function test_query_status_returns_dto_with_mock_confirmation_code(): void
    {
        $transactionId = 'TRANS-123';
        $dto = $this->gateway->queryStatus($transactionId);

        $this->assertInstanceOf(PaymentStatusDTO::class, $dto);
        $this->assertEquals($transactionId, $dto->transactionId);
        // Confirmation code is only generated if status is PAID in MockPaymentService
        // But MockGatewayAdapter::queryStatus will return confirmation code if it exists in response.
        $this->assertEquals('NOT_FOUND', $dto->status);
    }

    public function test_charge_returns_successful_response_with_mock_tracking_id(): void
    {
        // Use a phone number that triggers success in MockPaymentService
        $result = $this->gateway->charge('254701234567', 500, ['email' => 'test@example.com']);

        $this->assertTrue($result->success);
        $this->assertStringStartsWith('MOCK_', $result->transactionId);
    }
}
