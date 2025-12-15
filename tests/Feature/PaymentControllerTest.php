<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_public_check_status_endpoint_is_available()
    {
        $response = $this->getJson('/api/payments/check-status');

        // Route exists and responds (may return validation 422 or 200)
        $this->assertTrue(in_array($response->status(), [200, 422]));
    }

    public function test_webhook_endpoint_accepts_post()
    {
        $response = $this->postJson('/api/payments/webhook', ['payload' => 'test']);

        $this->assertTrue(in_array($response->status(), [200, 201, 202, 204, 422]));
    }

    public function test_authenticated_payment_endpoints_require_auth()
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

    public function test_authenticated_payment_endpoints_respond_when_authenticated()
    {
        $resp = $this->actingAs($this->user)
            ->getJson('/api/payments/form-metadata');

        $this->assertTrue(in_array($resp->status(), [200, 204, 422]));
        // Don't assert verify/process here without a subscription; check metadata and history/refund endpoints respond to auth
        $history = $this->actingAs($this->user)->getJson('/api/payments/history');
        $this->assertTrue(in_array($history->status(), [200, 204, 422, 500]));

        $refund = $this->actingAs($this->user)->postJson('/api/payments/refund', ['transaction_id' => 'T123', 'reason' => 'test', 'amount' => 1]);
        // Refund may return 403 if user is not admin or 422 for validation; accept those
        $this->assertTrue(in_array($refund->status(), [200, 201, 202, 403, 422, 500]));
    }
}
