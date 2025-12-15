<?php

namespace Tests\Unit;

use App\Services\PaymentGateway\PesapalGateway;
use Tests\TestCase;
use Illuminate\Support\Facades\Http;

class PesapalGatewayV3Test extends TestCase
{
    protected $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('payment.pesapal', [
            'consumer_key' => 'test_key',
            'consumer_secret' => 'test_secret',
            'environment' => 'sandbox',
            'api_base' => 'https://cybqa.pesapal.com/pesapalv3/api',
            'callback_url' => 'http://localhost:8000/payment/callback',
            'ipn_url' => 'http://localhost:8000/webhooks/pesapal/ipn',
            'ipn_notification_type' => 'POST',
        ]);

        $this->gateway = new PesapalGateway(config('payment.pesapal'));
    }

    /** @test */
    public function test_charge_successful_with_jwt_token()
    {
        Http::fake([
            'https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response([
                'token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test.signature',
                'expiryDate' => '2025-12-10T10:00:00Z',
                'status' => '200',
                'error' => null,
            ], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/URLSetup/GetIpnList' => Http::response([], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/URLSetup/RegisterIPN' => Http::response([
                'ipn_id' => '84740ab4-3cd9-47da-8a4f-dd1db53494b5',
                'url' => 'http://localhost:8000/webhooks/pesapal/ipn',
                'ipn_status' => 1,
                'status' => '200',
                'error' => null,
            ], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => 'b945e4af-80a5-4ec1-8706-e03f8332fb04',
                'merchant_reference' => 'customer_123',
                'redirect_url' => 'https://cybqa.pesapal.com/pesapaliframe/...',
                'status' => 'PENDING',
                'error' => null,
            ], 200),
        ]);

        $result = $this->gateway->charge('customer_123', 50000, [
            'email' => 'test@example.com',
            'phone' => '254712345678',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'description' => 'Membership renewal',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('PENDING', $result['status']);
        $this->assertEquals('b945e4af-80a5-4ec1-8706-e03f8332fb04', $result['reference']);
        $this->assertNotNull($result['redirect_url']);
    }

    /** @test */
    public function test_charge_fails_with_no_email_or_phone()
    {
        Http::fake([
            'https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response([
                'token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test.signature',
                'expiryDate' => '2025-12-10T10:00:00Z',
                'status' => '200',
                'error' => null,
            ], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/URLSetup/GetIpnList' => Http::response([], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/URLSetup/RegisterIPN' => Http::response([
                'ipn_id' => '84740ab4-3cd9-47da-8a4f-dd1db53494b5',
                'status' => '200',
            ], 200),
        ]);

        $result = $this->gateway->charge('customer_123', 50000, [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('FAILED', $result['status']);
    }

    /** @test */
    public function test_charge_fails_on_auth_token_failure()
    {
        Http::fake([
            'https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response([
                'token' => null,
                'error' => ['message' => 'Invalid credentials'],
                'status' => '401',
            ], 401),
        ]);

        $result = $this->gateway->charge('customer_123', 50000, [
            'email' => 'test@example.com',
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('authentication_failed', $result['status']);
    }

    /** @test */
    public function test_charge_fails_on_http_error()
    {
        Http::fake([
            'https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response([
                'token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test.signature',
                'status' => '200',
            ], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/URLSetup/GetIpnList' => Http::response([], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/URLSetup/RegisterIPN' => Http::response([
                'ipn_id' => '84740ab4-3cd9-47da-8a4f-dd1db53494b5',
                'status' => '200',
            ], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/Transactions/SubmitOrderRequest' => Http::response([
                'error' => ['message' => 'Server error'],
                'status' => '500',
            ], 500),
        ]);

        $result = $this->gateway->charge('customer_123', 50000, [
            'email' => 'test@example.com',
        ]);

        $this->assertFalse($result['success']);
    }

    /** @test */
    public function test_charge_with_json_response_format()
    {
        Http::fake([
            'https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response([
                'token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test.signature',
                'expiryDate' => '2025-12-10T10:00:00Z',
                'status' => '200',
            ], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/URLSetup/GetIpnList' => Http::response([], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/URLSetup/RegisterIPN' => Http::response([
                'ipn_id' => 'test-ipn-id',
                'status' => '200',
            ], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => 'order-123',
                'merchant_reference' => 'customer_123',
                'redirect_url' => 'https://payment.url',
                'status' => 'PENDING',
            ], 200),
        ]);

        $result = $this->gateway->charge('customer_123', 50000, [
            'email' => 'user@example.com',
            'phone' => '254712345678',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'description' => 'Annual membership',
        ]);

        // Verify all response fields are present
        $this->assertTrue($result['success']);
        $this->assertEquals('PENDING', $result['status']);
        $this->assertEquals('order-123', $result['reference']);
        $this->assertArrayHasKey('merchant_reference', $result);
        $this->assertArrayHasKey('redirect_url', $result);
        $this->assertArrayHasKey('raw', $result);
    }

    /** @test */
    public function test_get_transaction_status_completed()
    {
        Http::fake([
            'https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response([
                'token' => 'test_token',
                'status' => '200',
            ], 200),
            '**/Transactions/GetTransactionStatus*' => Http::response([
                'order_tracking_id' => 'order-123',
                'merchant_reference' => 'customer_123',
                'status' => 'COMPLETED',
                'confirmation_code' => 'AA11BB22',
                'payment_method' => 'MPESA',
                'payment_status_description' => 'Payment Received',
            ], 200),
        ]);

        $result = $this->gateway->getTransactionStatus('order-123', 'customer_123');

        $this->assertEquals('COMPLETED', $result['status']);
        $this->assertEquals('AA11BB22', $result['confirmation_code']);
        $this->assertEquals('MPESA', $result['payment_method']);
    }

    /** @test */
    public function test_gateway_initializes_with_config()
    {
        $customConfig = [
            'consumer_key' => 'custom_key',
            'consumer_secret' => 'custom_secret',
            'api_base' => 'https://custom.api',
        ];

        $gateway = new PesapalGateway($customConfig);
        
        // Gateway should accept custom config
        $this->assertInstanceOf(PesapalGateway::class, $gateway);
    }

    /** @test */
    public function test_gateway_loads_config_from_laravel()
    {
        // Should use config from service container
        $gateway = new PesapalGateway();
        
        $this->assertInstanceOf(PesapalGateway::class, $gateway);
    }

    /** @test */
    public function test_charge_with_billing_address_fields()
    {
        Http::fake([
            'https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response([
                'token' => 'test_token',
                'status' => '200',
            ], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/URLSetup/GetIpnList' => Http::response([], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/URLSetup/RegisterIPN' => Http::response([
                'ipn_id' => 'test-ipn',
                'status' => '200',
            ], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => 'order-456',
                'merchant_reference' => 'customer_456',
                'redirect_url' => 'https://payment.url',
                'status' => 'PENDING',
            ], 200),
        ]);

        $result = $this->gateway->charge('customer_456', 100000, [
            'email' => 'user@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address_line_1' => '123 Main St',
            'city' => 'Nairobi',
            'state' => 'KE',
            'postal_code' => '00100',
            'description' => 'Event registration',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('order-456', $result['reference']);
    }
}
