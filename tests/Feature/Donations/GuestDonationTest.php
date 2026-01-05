<?php

namespace Tests\Feature\Donations;

use App\Models\County;
use App\Models\Donation;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\DonationReceived;
use App\Services\PaymentGateway\GatewayManager;
use App\DTOs\Payments\PaymentStatusDTO;
use App\DTOs\Payments\TransactionResultDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GuestDonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        County::factory()->create(['name' => 'Nairobi']);
        
        // Essential for tests that fall back to admin user
        User::factory()->create([
            'email' => 'admin@dadisilab.com',
            'username' => 'admin'
        ]);
    }

    /**
     * Test a guest can initiate a donation.
     */
    public function test_guest_can_initiate_donation()
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Guest',
            'email' => 'guest@example.com',
            'amount' => 1000,
            'currency' => 'KES',
            'county_id' => County::first()->id,
        ];

        $response = $this->postJson('/api/donations', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['donation_id', 'reference', 'redirect_url']
            ]);

        $this->assertDatabaseHas('donations', [
            'donor_email' => 'guest@example.com',
            'user_id' => null,
            'status' => 'pending'
        ]);
    }

    /**
     * Test guest receives email on payment completion.
     */
    public function test_guest_receives_email_on_payment_completion()
    {
        Notification::fake();

        $donation = Donation::factory()->create([
            'user_id' => null,
            'donor_email' => 'guest@example.com',
            'donor_name' => 'John Guest',
            'status' => 'pending',
            'amount' => 1000,
        ]);

        $payment = Payment::create([
            'payable_id' => $donation->id,
            'payable_type' => Donation::class,
            'payer_id' => null,
            'amount' => 1000,
            'currency' => 'KES',
            'status' => 'pending',
            'transaction_id' => 'GUEST_TX_123',
            'order_reference' => 'GUEST_ORDER_123',
            'gateway' => 'mock'
        ]);

        // Mock gateway status check returning PaymentStatusDTO
        $mockResult = new PaymentStatusDTO(
            'GUEST_TX_123', 
            'GUEST_ORDER_123', 
            'COMPLETED', 
            1000, 
            'KES'
        );
        
        $this->mock(GatewayManager::class, function ($mock) use ($mockResult) {
            $mock->shouldReceive('queryStatus')->andReturn($mockResult);
        });

        // Verify payment as an admin/system actor
        $admin = User::first(); // Use the one from setUp
        $response = $this->actingAs($admin)->postJson('/api/payments/verify', [
            'transaction_id' => 'GUEST_TX_123'
        ]);

        $response->assertStatus(200);

        // Assert notification was sent to the guest email address
        Notification::assertSentOnDemand(
            DonationReceived::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'guest@example.com';
            }
        );

        $this->assertEquals('paid', $donation->fresh()->status);
    }

    /**
     * Test guest can resume pending donation payment.
     */
    public function test_guest_can_resume_pending_donation_payment()
    {
        $donation = Donation::factory()->create([
            'status' => 'pending',
            'reference' => 'DON-RESUME-123',
            'user_id' => null,
            'donor_email' => 'guest@example.com',
            'donor_name' => 'John Guest',
        ]);

        // Mock gateway for initiation
        $mockResult = TransactionResultDTO::success('NEW_TX_456', 'MERCH_789', 'PENDING', 'Redirecting...');
        $mockResult->redirectUrl = 'https://pesapal.com/checkout/NEW_TX_456';
        
        $this->mock(GatewayManager::class, function ($mock) use ($mockResult) {
            $mock->shouldReceive('initiatePayment')->andReturn($mockResult);
        });

        $response = $this->postJson("/api/donations/ref/{$donation->reference}/resume");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'redirect_url' => 'https://pesapal.com/checkout/NEW_TX_456',
                    'transaction_id' => 'NEW_TX_456'
                ]
            ]);
    }

    /**
     * Test guest can cancel pending donation.
     */
    public function test_guest_can_cancel_pending_donation()
    {
        $donation = Donation::factory()->create([
            'status' => 'pending',
            'reference' => 'DON-CANCEL-123',
            'user_id' => null,
        ]);

        $response = $this->postJson("/api/donations/ref/{$donation->reference}/cancel");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals('cancelled', $donation->fresh()->status);
        
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'guest_cancelled_donation',
            'model_id' => $donation->id
        ]);
    }
}
