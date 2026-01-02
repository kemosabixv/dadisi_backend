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
        \Spatie\Permission\Models\Role::create(['name' => 'admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->service = new RefundService(
            $this->createMock(\App\Services\PaymentGateway\GatewayManager::class)
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
        $event = Event::factory()->create(['organizer_id' => $user->id]);
        
        $order = EventOrder::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'paid',
        ]);

        $payment = Payment::factory()->create([
            'payable_type' => 'event_order',
            'payable_id' => $order->id,
            'status' => 'paid',
        ]);

        // Create first refund
        Refund::create([
            'refundable_type' => EventOrder::class,
            'refundable_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => $order->total_amount,
            'original_amount' => $order->total_amount,
            'currency' => 'KES',
            'status' => Refund::STATUS_PENDING,
            'reason' => 'cancellation',
            'requested_at' => now(),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('A refund is already pending for this order.');

        $this->service->requestEventOrderRefund($order, 'duplicate');
    }

    public function test_refund_amount_cannot_exceed_original(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['organizer_id' => $user->id]);
        
        $order = EventOrder::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'paid',
            'total_amount' => 1000,
        ]);

        Payment::factory()->create([
            'payable_type' => 'event_order',
            'payable_id' => $order->id,
            'status' => 'paid',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Refund amount cannot exceed the original payment amount.');

        $this->service->requestEventOrderRefund($order, 'cancellation', null, 2000);
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

        $this->assertEquals('Event Cancellation', $refund->reason_display);
    }
}
