<?php

namespace Tests\Feature\Api;

use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\SystemFeature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LabBookingPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
    }

    /**
     * Helper to perform an authenticated request using a manual token.
     */
    private function authenticatedRequest(User $user, string $method, string $uri, array $data = []): TestResponse
    {
        return $this->actingAs($user)->json($method, $uri, $data);
    }

    /**
     * Helper to setup a plan with lab hours for a user.
     */
    private function setupUserWithLabPlan(User $user, int $hours = 10): void
    {
        $feature = SystemFeature::updateOrCreate(
            ['slug' => 'lab_hours_monthly'],
            [
                'name' => 'Monthly Lab Hours',
                'value_type' => 'number',
                'default_value' => '0',
                'is_active' => true
            ]
        );

        $plan = Plan::factory()->create(['name' => ['en' => 'Test Plan']]);
        $plan->systemFeatures()->sync([$feature->id => ['value' => (string)$hours]]);

        $user->plan_id = $plan->id;
        $user->save();

        $user->subscriptions()->create([
            'plan_id' => $plan->id,
            'name' => 'Main Subscription',
            'subscriber_type' => 'user',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $user->refresh();
    }

    public function test_guest_can_initiate_lab_booking_payment(): void
    {
        Notification::fake();
        $lab = LabSpace::factory()->create(['capacity' => 10, 'hourly_rate' => 500]);
        $startTime = now()->addDays(1)->setTime(10, 0, 0);
        $endTime = now()->addDays(1)->setTime(12, 0, 0);

        $response = $this->postJson('/api/bookings/guest', [
            'lab_space_id' => $lab->id,
            'starts_at' => $startTime->toDateTimeString(),
            'ends_at' => $endTime->toDateTimeString(),
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '123456789',
            'purpose' => 'Research Project',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => ['id'],
                'redirect_url',
                'transaction_id',
            ]);

        $this->assertDatabaseHas('lab_bookings', [
            'guest_email' => 'john@example.com',
            'status' => LabBooking::STATUS_PENDING,
        ]);

        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable,
            \App\Notifications\LabBookingInitiated::class
        );
    }

    public function test_authenticated_user_can_initiate_lab_booking_payment(): void
    {
        $user = User::factory()->create();
        $this->setupUserWithLabPlan($user);

        $lab = LabSpace::factory()->create(['capacity' => 10, 'hourly_rate' => 300, 'is_available' => true]);
        $startTime = now()->addDays(2)->setTime(14, 0, 0);
        $endTime = now()->addDays(2)->setTime(15, 0, 0);

        // Make the request
        $response = $this->authenticatedRequest($user, 'POST', '/api/bookings', [
            'lab_space_id' => $lab->id,
            'starts_at' => $startTime->toDateTimeString(),
            'ends_at' => $endTime->toDateTimeString(),
            'purpose' => 'Study session',
        ]);

        $response->assertStatus(201);
        $this->assertEquals(LabBooking::STATUS_CONFIRMED, $response->json('data.status'));

        $this->assertDatabaseHas('lab_bookings', [
            'user_id' => $user->id,
            'status' => LabBooking::STATUS_CONFIRMED,
        ]);
    }

    public function test_payment_verification_approves_lab_booking(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->setupUserWithLabPlan($user);
        
        $lab = LabSpace::factory()->create();
        
        $booking = LabBooking::factory()->create([
            'user_id' => $user->id,
            'lab_space_id' => $lab->id,
            'status' => LabBooking::STATUS_PENDING,
            'total_price' => 500,
        ]);

        $payment = Payment::factory()->create([
            'payable_type' => 'lab_booking',
            'payable_id' => $booking->id,
            'amount' => 500,
            'status' => 'pending',
            'external_reference' => 'TEST_REF_123',
            'gateway' => 'pesapal',
        ]);

        // Mock the gateway to return success
        $gatewayManager = $this->createMock(\App\Services\PaymentGateway\GatewayManager::class);
        $gatewayManager->method('queryStatus')->willReturn(
            new \App\DTOs\Payments\PaymentStatusDTO(
                'MOCK_TRANS_123',
                $payment->external_reference,
                'COMPLETED',
                500,
                'KES'
            )
        );
        $this->app->instance(\App\Services\PaymentGateway\GatewayManager::class, $gatewayManager);

        $paymentService = app(\App\Services\Contracts\PaymentServiceContract::class);
        $paymentService->verifyPayment($user, $payment->external_reference);

        $this->assertEquals(LabBooking::STATUS_CONFIRMED, $booking->fresh()->status);
        $this->assertTrue($booking->fresh()->quota_consumed);
        
        Notification::assertSentTo($user, \App\Notifications\LabBookingConfirmation::class);
    }

    public function test_failed_payment_keeps_booking_pending(): void
    {
        $user = User::factory()->create();
        $this->setupUserWithLabPlan($user);
        
        $lab = LabSpace::factory()->create();
        
        $booking = LabBooking::factory()->create([
            'user_id' => $user->id,
            'status' => LabBooking::STATUS_PENDING,
        ]);

        $payment = Payment::factory()->create([
            'payable_type' => 'lab_booking',
            'payable_id' => $booking->id,
            'status' => 'pending',
            'external_reference' => 'FAIL_REF_456',
            'gateway' => 'pesapal',
        ]);

        // Mock the gateway to return failure
        $gatewayManager = $this->createMock(\App\Services\PaymentGateway\GatewayManager::class);
        $gatewayManager->method('queryStatus')->willReturn(
            new \App\DTOs\Payments\PaymentStatusDTO(
                'FAIL_REF_456',
                $payment->external_reference,
                'FAILED',
                500,
                'KES'
            )
        );
        
        $this->app->instance(\App\Services\PaymentGateway\GatewayManager::class, $gatewayManager);

        $paymentService = app(\App\Services\Contracts\PaymentServiceContract::class);
        $paymentService->verifyPayment($user, $payment->external_reference);

        $this->assertEquals(LabBooking::STATUS_PENDING, $booking->fresh()->status);
        $this->assertFalse($booking->fresh()->quota_consumed);
    }
}
