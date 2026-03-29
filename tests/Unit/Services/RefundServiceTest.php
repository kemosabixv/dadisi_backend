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

class RefundServiceTest extends TestCase
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

    public function test_cannot_request_refund_for_unpaid_order(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['organizer_id' => $user->id]);
        
        $order = EventOrder::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only paid orders can be refunded.');

        $this->service->requestEventOrderRefund($order, 'cancellation');
    }

    public function test_cannot_request_duplicate_refund(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        
        $order = EventOrder::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => 'paid',
        ]);

        $payment = Payment::factory()->create([
            'payable_type' => 'event_order',
            'payable_id' => $order->id,
            'status' => 'paid',
            'amount' => 1000,
        ]);

        // Create first refund
        $this->service->requestEventOrderRefund($order, 'cancellation');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('A refund is already pending for this order.');

        $this->service->requestEventOrderRefund($order, 'duplicate');
    }

    public function test_refund_is_always_for_full_amount(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        
        $order = EventOrder::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => 'paid',
            'total_amount' => 1000,
        ]);

        Payment::factory()->create([
            'payable_type' => 'event_order',
            'payable_id' => $order->id,
            'status' => 'paid',
            'amount' => 1000,
        ]);

        $refund = $this->service->requestEventOrderRefund($order, 'cancellation', null, 500);
        
        $this->assertEquals(1000, $refund->amount);
    }

    public function test_refund_status_workflow(): void
    {
        $user = User::factory()->create();
        $approver = User::factory()->create();
        $approver->assignRole('admin');

        $refund = Refund::factory()->create([
            'status' => Refund::STATUS_PENDING,
        ]);

        // Test isPending
        $this->assertTrue($refund->isPending());
        $this->assertFalse($refund->isCompleted());

        // Approve
        $refund->approve($approver);
        $refund->refresh();
        
        $this->assertEquals(Refund::STATUS_APPROVED, $refund->status);
        $this->assertNotNull($refund->approved_at);

        // Mark completed
        $refund->markCompleted('TEST-123');
        $refund->refresh();

        $this->assertEquals(Refund::STATUS_COMPLETED, $refund->status);
        $this->assertTrue($refund->isCompleted());
    }

    public function test_refund_percentage_calculation(): void
    {
        $refund = Refund::factory()->create([
            'amount' => 250,
            'original_amount' => 1000,
        ]);

        $this->assertEquals(25.0, $refund->refund_percentage);
    }

    public function test_refund_reason_display(): void
    {
        $refund = Refund::factory()->create([
            'reason' => Refund::REASON_CANCELLATION,
        ]);

        $this->assertEquals('Cancellation', $refund->reason_display);
    }

    public function test_complete_refund_updates_payment_record(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['organizer_id' => $user->id]);
        
        $order = EventOrder::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'paid',
            'total_amount' => 1000,
        ]);

        $payment = Payment::factory()->create([
            'payable_type' => 'event_order',
            'payable_id' => $order->id,
            'status' => 'paid',
            'amount' => 1000,
        ]);

        $refund = Refund::factory()->create([
            'refundable_type' => EventOrder::class,
            'refundable_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => 1000,
            'original_amount' => 1000,
            'status' => Refund::STATUS_APPROVED,
            'processed_by' => $this->admin->id,
            'reason' => Refund::REASON_CANCELLATION,
        ]);

        // Process refund (in mock mode this calls completeRefund)
        $this->service->processRefund($refund);

        $payment->refresh();
        $refund->refresh();

        $this->assertEquals(Refund::STATUS_COMPLETED, $refund->status);
        $this->assertEquals('refunded', $payment->status);
        $this->assertNotNull($payment->refunded_at);
        $this->assertEquals($this->admin->id, $payment->refunded_by);
        $this->assertEquals(Refund::REASON_CANCELLATION, $payment->refund_reason);
    }

    public function test_process_refund_prioritizes_confirmation_code_for_gateway(): void
    {
        $payment = Payment::factory()->create([
            'status' => 'paid',
            'transaction_id' => 'PESAPAL_TRK_001',
            'confirmation_code' => 'MPESA_REF_123',
            'gateway' => 'pesapal',
        ]);

        $refund = Refund::factory()->create([
            'payment_id' => $payment->id,
            'amount' => 1000,
            'status' => Refund::STATUS_APPROVED,
        ]);

        // Mock the Gateway and GatewayManager
        $gatewayMock = $this->createMock(\App\Services\Contracts\PaymentGatewayContract::class);
        $gatewayManagerMock = $this->createMock(\App\Services\PaymentGateway\GatewayManager::class);
        
        $gatewayManagerMock->method('gateway')->with('pesapal')->willReturn($gatewayMock);
        
        // Verify that 'refund' is called with 'MPESA_REF_123' (confirmation_code) 
        // as the first argument, NOT 'PESAPAL_TRK_001'
        $gatewayMock->expects($this->once())
            ->method('refund')
            ->with(
                $this->equalTo('MPESA_REF_123'), 
                $this->equalTo(1000), 
                $this->anything()
            )
            ->willReturn(['success' => true, 'refund_id' => 'R-123']);

        $service = new RefundService(
            $gatewayManagerMock,
            $this->createMock(\App\Services\Contracts\NotificationServiceContract::class)
        );

        $service->processRefund($refund);
    }
}
