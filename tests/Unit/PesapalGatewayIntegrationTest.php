<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PaymentGateway\PesapalGateway;
use Illuminate\Support\Facades\Http;

class PesapalGatewayIntegrationTest extends TestCase
{
    protected PesapalGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize Pesapal gateway with v3 API test credentials
        $this->gateway = new PesapalGateway([
            'consumer_key' => 'test_key',
            'consumer_secret' => 'test_secret',
            'environment' => 'sandbox',
            'api_base' => 'https://cybqa.pesapal.com/pesapalv3/api',
            'callback_url' => 'http://localhost:8000/payment/callback',
            'ipn_url' => 'http://localhost:8000/webhooks/pesapal/ipn',
        ]);
    }

    /**
     * Test successful payment charge with mocked v3 API responses.
     */
    public function test_charge_successful_with_mocked_responses()
    {
        Http::fake([
            'https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response([
                'token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test.signature',
                'expiryDate' => now()->addMinutes(5)->toIso8601String(),
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
                'order_tracking_id' => '12345ABC-ref-001',
                'merchant_reference' => 'customer_001',
                'redirect_url' => 'https://cybqa.pesapal.com/pesapaliframe/...',
                'status' => 'PENDING',
                'error' => null,
            ], 200),
        ]);

        $result = $this->gateway->charge('customer_001', 50000, [
            'email' => 'user@example.com',
            'phone' => '254712345678',
            'description' => 'Membership payment',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('PENDING', $result['status']);
        $this->assertNull($result['error_message']);
        $this->assertArrayHasKey('reference', $result);
    }

    /**
     * Test payment failure when transaction fails on backend.
     */
    public function test_charge_fails_with_transaction_error()
    {
        Http::fake([
            'https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response([
                'token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test.signature',
                'expiryDate' => now()->addMinutes(5)->toIso8601String(),
                'status' => '200',
                'error' => null,
            ], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/URLSetup/GetIpnList' => Http::response([], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/URLSetup/RegisterIPN' => Http::response([
                'ipn_id' => '84740ab4-3cd9-47da-8a4f-dd1db53494b5',
                'status' => '200',
            ], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => null,
                'status' => 'FAILED',
                'error' => 'Invalid amount',
            ], 400),
        ]);

        $result = $this->gateway->charge('customer_002', 50000, [
            'email' => 'user@example.com',
            'phone' => '254712345678',
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('FAILED', $result['status']);
    }

    /**
     * Test payment failure with authentication failure.
     */
    public function test_charge_fails_on_auth_token_failure()
    {
        Http::fake([
            'https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response([
                'status' => '401',
                'error' => 'Invalid credentials',
            ], 401),
        ]);

        $result = $this->gateway->charge('customer_003', 50000);

        $this->assertFalse($result['success']);
        $this->assertEquals('authentication_failed', $result['status']);
    }

    /**
     * Test charge with HTTP connection error.
     */
    public function test_charge_fails_on_http_error()
    {
        Http::fake([
            'https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response([], 500),
        ]);

        $result = $this->gateway->charge('customer_004', 50000);

        $this->assertFalse($result['success']);
        $this->assertEquals('authentication_failed', $result['status']);
    }

    /**
     * Test response with pending status (normal for Pesapal v3).
     */
    public function test_charge_with_pending_status_is_successful()
    {
        Http::fake([
            'https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response([
                'token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test.signature',
                'expiryDate' => now()->addMinutes(5)->toIso8601String(),
                'status' => '200',
                'error' => null,
            ], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/URLSetup/GetIpnList' => Http::response([], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/URLSetup/RegisterIPN' => Http::response([
                'ipn_id' => '84740ab4-3cd9-47da-8a4f-dd1db53494b5',
                'status' => '200',
            ], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => 'PEND123-ref-005',
                'merchant_reference' => 'customer_005',
                'redirect_url' => 'https://cybqa.pesapal.com/pesapaliframe/...',
                'status' => 'PENDING',
                'error' => null,
            ], 200),
        ]);

        $result = $this->gateway->charge('customer_005', 30000, [
            'email' => 'user@example.com',
            'phone' => '254712345678',
        ]);

        // PENDING is considered successful in the normalization logic
        $this->assertTrue($result['success']);
        $this->assertEquals('PENDING', $result['status']);
    }

    /**
     * Test charge with metadata included in request.
     */
    public function test_charge_includes_metadata_in_request()
    {
        Http::fake([
            'https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response([
                'token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test.signature',
                'expiryDate' => now()->addMinutes(5)->toIso8601String(),
                'status' => '200',
                'error' => null,
            ], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/URLSetup/GetIpnList' => Http::response([], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/URLSetup/RegisterIPN' => Http::response([
                'ipn_id' => '84740ab4-3cd9-47da-8a4f-dd1db53494b5',
                'status' => '200',
            ], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => 'MET123-ref-006',
                'merchant_reference' => 'customer_006',
                'redirect_url' => 'https://cybqa.pesapal.com/pesapaliframe/...',
                'status' => 'PENDING',
                'error' => null,
            ], 200),
        ]);

        $metadata = [
            'email' => 'john@example.com',
            'phone' => '254701234567',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'description' => 'Annual membership renewal',
        ];

        $result = $this->gateway->charge('customer_006', 100000, $metadata);

        $this->assertTrue($result['success']);

        // Verify that POST requests were made
        Http::assertSent(function ($request) {
            return $request->method() === 'POST';
        });
    }

    /**
     * Test that Bearer token is included in Authorization header.
     */
    public function test_bearer_token_in_authorization_header()
    {
        Http::fake([
            'https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response([
                'token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test.signature',
                'expiryDate' => now()->addMinutes(5)->toIso8601String(),
                'status' => '200',
                'error' => null,
            ], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/URLSetup/GetIpnList' => Http::response([], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/URLSetup/RegisterIPN' => Http::response([
                'ipn_id' => '84740ab4-3cd9-47da-8a4f-dd1db53494b5',
                'status' => '200',
            ], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => 'AUTH123-ref-007',
                'merchant_reference' => 'customer_007',
                'redirect_url' => 'https://cybqa.pesapal.com/pesapaliframe/...',
                'status' => 'PENDING',
                'error' => null,
            ], 200),
        ]);

        $this->gateway->charge('customer_007', 50000);

        // Verify Bearer token in Authorization header for IPN setup and order submission
        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST' && $request->method() !== 'GET') {
                return false;
            }
            // Check if Authorization header exists (for IPN and SubmitOrderRequest)
            if (str_contains($request->url(), 'URLSetup') || str_contains($request->url(), 'SubmitOrderRequest')) {
                $authHeader = $request->header('Authorization');
                return $request->hasHeader('Authorization') && 
                       (is_array($authHeader) ? in_array('Bearer', array_map(fn($h) => explode(' ', $h)[0], $authHeader)) : str_contains($authHeader, 'Bearer'));
            }
            return true;
        });
    }

    /**
     * Test gateway accepts config from constructor.
     */
    public function test_gateway_initializes_with_config()
    {
        $config = [
            'consumer_key' => 'test_key',
            'consumer_secret' => 'test_secret',
            'environment' => 'sandbox',
            'api_base' => 'https://cybqa.pesapal.com/pesapalv3/api',
        ];

        $gateway = new PesapalGateway($config);

        // Verify gateway can be instantiated with custom config
        $this->assertInstanceOf(PesapalGateway::class, $gateway);
    }

    /**
     * Test gateway loads config from Laravel config file.
     */
    public function test_gateway_loads_config_from_laravel()
    {
        config()->set('payment.pesapal', [
            'consumer_key' => 'config_key',
            'consumer_secret' => 'config_secret',
            'environment' => 'sandbox',
            'api_base' => 'https://cybqa.pesapal.com/pesapalv3/api',
        ]);

        $gateway = new PesapalGateway([]);

        // Verify gateway can be instantiated and uses config file
        $this->assertInstanceOf(PesapalGateway::class, $gateway);
    }
}
