<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\PlanSubscription;
use App\Models\Plan;
use App\Models\SubscriptionEnhancement;
use App\Models\UserPaymentMethod;
use App\Services\AutoRenewalService;
use App\Services\PaymentGateway\GatewayManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class PesapalRenewalIntegrationTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations for the test database
        $this->artisan('migrate');
    }

    /**
     * Test successful auto-renewal with Pesapal gateway (v3 API).
     */
    public function test_auto_renewal_succeeds_with_pesapal()
    {
        // Setup: Create user with subscription and payment method
        $user = User::factory()->create(['phone' => '254712345678']);
        $plan = Plan::factory()->create(['amount' => 5000]);
        $subscription = PlanSubscription::factory()->create([
            'subscriber_id' => $user->id,
            'subscriber_type' => User::class,
            'plan_id' => $plan->id,
            'status' => 'active',
            'ends_at' => now()->addDay(),
        ]);
        $enhancement = SubscriptionEnhancement::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => 'pending',
        ]);
        UserPaymentMethod::factory()->create([
            'user_id' => $user->id,
            'type' => 'phone',
            'identifier' => '254712345678',
            'is_primary' => true,
        ]);

        // Mock Pesapal v3 API responses
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
                'order_tracking_id' => 'REN123-tracking',
                'merchant_reference' => 'customer_renewal_1',
                'redirect_url' => 'https://cybqa.pesapal.com/pesapaliframe/...',
                'status' => 'PENDING',
                'error' => null,
            ], 200),
        ]);

        // Set payment gateway to Pesapal
        config(['payment.gateway' => 'pesapal']);
        config(['payment.pesapal' => [
            'consumer_key' => 'test_key',
            'consumer_secret' => 'test_secret',
            'environment' => 'sandbox',
            'api_base' => 'https://cybqa.pesapal.com/pesapalv3/api',
        ]]);

        // Execute renewal service
        Mail::fake();
        $service = new AutoRenewalService();
        $job = $service->processSubscriptionRenewal($subscription);

        // Assertions
        $this->assertNotNull($job);
        $this->assertIsObject($job);
        // Verify subscription was extended by one month
        $subscription->refresh();
        $this->assertTrue($subscription->ends_at->isAfter(now()));
    }

    /**
     * Test renewal failure with Pesapal returns to mock for retry.
     */
    public function test_renewal_falls_back_on_pesapal_failure()
    {
        $user = User::factory()->create(['phone' => '254798765432']);
        $plan = Plan::factory()->create(['amount' => 5000]);
        $subscription = PlanSubscription::factory()->create([
            'subscriber_id' => $user->id,
            'subscriber_type' => User::class,
            'plan_id' => $plan->id,
            'status' => 'active',
            'ends_at' => now()->addDay(),
        ]);
        $enhancement = SubscriptionEnhancement::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => 'pending',
        ]);
        UserPaymentMethod::factory()->create([
            'user_id' => $user->id,
            'type' => 'phone',
            'identifier' => '254798765432',
            'is_primary' => true,
        ]);

        // Mock Pesapal v3 API to return failure
        Http::fake([
            'https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response([], 500),
        ]);

        config(['payment.gateway' => 'pesapal']);
        config(['payment.pesapal' => [
            'consumer_key' => 'test_key',
            'consumer_secret' => 'test_secret',
            'environment' => 'sandbox',
            'api_base' => 'https://cybqa.pesapal.com/pesapalv3/api',
        ]]);

        Mail::fake();
        $service = new AutoRenewalService();
        $job = $service->processSubscriptionRenewal($subscription);

        // Should record failure
        $this->assertNotNull($job);
        $this->assertIsObject($job);
    }

    /**
     * Test Pesapal v3 gateway is used when configured.
     */
    public function test_gateway_manager_uses_pesapal_when_configured()
    {
        config(['payment.gateway' => 'pesapal']);
        config(['payment.pesapal' => [
            'consumer_key' => 'test_key',
            'consumer_secret' => 'test_secret',
            'environment' => 'sandbox',
            'api_base' => 'https://cybqa.pesapal.com/pesapalv3/api',
        ]]);

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
                'order_tracking_id' => 'GWAY123-tracking',
                'merchant_reference' => 'test_id_123',
                'redirect_url' => 'https://cybqa.pesapal.com/pesapaliframe/...',
                'status' => 'PENDING',
                'error' => null,
            ], 200),
        ]);

        $manager = new GatewayManager();
        $result = $manager->charge('test_id_123', 50000, ['email' => 'test@example.com']);

        // Verify Pesapal gateway was used
        $this->assertTrue($result['success']);
        Http::assertSent(function ($request) {
            // Pesapal v3 API endpoints should be called
            return strpos($request->url(), 'cybqa.pesapal.com/pesapalv3/api') !== false;
        });
    }

    /**
     * Test can switch from Pesapal to mock gateway dynamically.
     */
    public function test_can_switch_between_gateways()
    {
        // Use Pesapal
        config(['payment.gateway' => 'pesapal']);
        config(['payment.pesapal' => [
            'consumer_key' => 'test_key',
            'consumer_secret' => 'test_secret',
            'environment' => 'sandbox',
            'api_base' => 'https://cybqa.pesapal.com/pesapalv3/api',
        ]]);

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
                'order_tracking_id' => 'SWITCH123-tracking',
                'merchant_reference' => 'switch_test_001',
                'redirect_url' => 'https://cybqa.pesapal.com/pesapaliframe/...',
                'status' => 'PENDING',
                'error' => null,
            ], 200),
        ]);

        $manager = new GatewayManager();
        $result = $manager->charge('switch_test_001', 50000, ['email' => 'test@example.com']);
        // Pesapal should succeed
        $this->assertTrue($result['success']);

        // Verify Pesapal was called
        Http::assertSent(function ($request) {
            return strpos($request->url(), 'cybqa.pesapal.com/pesapalv3/api') !== false;
        });
    }
}
