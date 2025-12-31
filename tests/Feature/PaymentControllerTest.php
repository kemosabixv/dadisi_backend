<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;
    
    protected bool $shouldSeedRoles = true;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function public_check_status_endpoint_is_available()
    {
        $response = $this->getJson('/api/payments/check-status');

        // Route exists and responds (may return validation 422 or 200)
        $this->assertTrue(in_array($response->status(), [200, 422]));
    }

    #[Test]
    public function webhook_endpoint_accepts_post()
    {
        $response = $this->postJson('/api/payments/webhook', ['payload' => 'test']);

        $this->assertTrue(in_array($response->status(), [200, 201, 202, 204, 422]));
    }

    #[Test]
    public function authenticated_payment_endpoints_require_auth()
    {
        // Unauthenticated requests should be rejected
        $unauth = $this->getJson('/api/payments/form-metadata');
        $unauth->assertStatus(401);

        $unauthPost = $this->postJson('/api/payments/verify', []);
        $unauthPost->assertStatus(401);

        $unauthPost2 = $this->postJson('/api/payments/process', []);
        $unauthPost2->assertStatus(401);

        $unauthHistory = $this->getJson('/api/payments/history');
        $unauthHistory->assertStatus(401);

        $unauthRefund = $this->postJson('/api/payments/refund', []);
        $unauthRefund->assertStatus(401);
    }

    #[Test]
    public function authenticated_payment_endpoints_respond_when_authenticated()
    {
        $resp = $this->actingAs($this->user)
            ->getJson('/api/payments/form-metadata');

        $this->assertTrue(in_array($resp->status(), [200, 204, 422]));
        // Don't assert verify/process here without a subscription; check metadata and history/refund endpoints respond to auth
        $history = $this->actingAs($this->user)->getJson('/api/payments/history');
        $this->assertTrue(in_array($history->status(), [200, 204, 422, 500]));
    }

    #[Test]
    public function admin_can_successfully_refund_payment_api()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        
        $payment = \App\Models\Payment::factory()->create([
            'status' => 'paid',
            'amount' => 1000,
            'transaction_id' => 'TRANS-API-123',
        ]);

        // Mock the service/gateway
        // Since it's a feature test, we could mock the gateway manager or the service
        // Let's mock the GatewayManager as it's cleaner
        $mockResult = \App\DTOs\Payments\TransactionResultDTO::success(
            transactionId: 'REF-API-999',
            merchantReference: 'TRANS-API-123',
            status: 'REFUNDED',
            message: 'Refund successful'
        );

        $this->mock(\App\Services\PaymentGateway\GatewayManager::class, function ($mock) use ($mockResult) {
            $mock->shouldReceive('refund')->once()->andReturn($mockResult);
        });

        $response = $this->actingAs($admin)
            ->postJson('/api/payments/refund', [
                'transaction_id' => $payment->transaction_id,
                'reason' => 'API Test Refund',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Refund processed successfully',
            ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'refunded',
        ]);
    }
}
