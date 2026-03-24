<?php

namespace Tests\Unit\Services;

use App\Models\Refund;
use App\Models\EventOrder;
use App\Models\Payment;
use App\Models\Event;
use App\Models\User;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentRefundTest extends TestCase
{
    use RefreshDatabase;

    private RefundService $service;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->service = new RefundService(
            $this->createMock(\App\Services\PaymentGateway\GatewayManager::class),
            $this->createMock(\App\Services\Contracts\NotificationServiceContract::class)
        );
    }

    public function test_payment_record_is_correctly_updated_on_full_refund(): void
    {
        // 1. Setup Payment & Refund
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $order = EventOrder::factory()->create(['user_id' => $user->id, 'event_id' => $event->id, 'status' => 'paid']);
        
        $payment = Payment::factory()->create([
            'payable_type' => 'event_order',
            'payable_id' => $order->id,
            'status' => 'paid',
            'amount' => 1000,
        ]);

        $refund = Refund::factory()->create([
            'payment_id' => $payment->id,
            'refundable_type' => EventOrder::class,
            'refundable_id' => $order->id,
            'amount' => 1000,
            'original_amount' => 1000,
            'status' => Refund::STATUS_APPROVED,
            'processed_by' => $this->admin->id,
            'reason' => Refund::REASON_CUSTOMER_REQUEST,
        ]);

        // 2. Act
        $this->service->processRefund($refund);

        // 3. Assert
        $payment->refresh();
        $this->assertEquals('refunded', $payment->status);
        $this->assertNotNull($payment->refunded_at);
        $this->assertEquals($this->admin->id, $payment->refunded_by);
        $this->assertEquals(Refund::REASON_CUSTOMER_REQUEST, $payment->refund_reason);
    }
}
