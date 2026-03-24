<?php

namespace Tests\Unit;

use App\Models\EventOrder;
use App\Models\EventRegistration;
use App\Models\Ticket;
use App\Models\Payment;
use App\Models\User;
use App\Models\Event;
use App\Services\Payments\MockPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Illuminate\Support\Str;

class PaymentStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that EventOrder and EventRegistration are synchronized when paid via MockPaymentService.
     */
    public function test_mock_payment_syncs_order_and_registrations()
    {
        // 1. Setup: Create Event, Ticket, User, Order, and Registration
        $event = Event::factory()->create();
        $ticket = Ticket::factory()->create([
            'event_id' => $event->id,
            'available' => 10,
        ]);
        
        $user = User::factory()->create();
        $order = EventOrder::factory()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'ticket_id' => $ticket->id,
            'status' => 'pending',
            'quantity' => 2,
            'waitlist_position' => 1,
        ]);

        $reg1 = EventRegistration::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'event_id' => $event->id,
            'ticket_id' => $ticket->id,
            'status' => 'pending',
            'waitlist_position' => 1,
            'confirmation_code' => Str::upper(Str::random(10)),
        ]);

        $reg2 = EventRegistration::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'event_id' => $event->id,
            'ticket_id' => $ticket->id,
            'status' => 'pending',
            'waitlist_position' => 1,
            'confirmation_code' => Str::upper(Str::random(10)),
        ]);

        $payment = Payment::create([
            'payable_type' => 'event_order',
            'payable_id' => $order->id,
            'payer_id' => $user->id,
            'status' => 'pending',
            'amount' => 1000,
            'currency' => 'KES',
            'gateway' => 'mock',
            'order_reference' => 'ORD-TEST-123',
        ]);

        // 2. Action: Call the protected activatePayable via reflection
        $reflection = new \ReflectionClass(MockPaymentService::class);
        $method = $reflection->getMethod('activatePayable');
        $method->setAccessible(true);
        $method->invoke(null, $payment);

        // 3. Assert: Verify statuses are updated and waitlist cleared
        $order->refresh();
        $this->assertEquals('paid', $order->status);
        $this->assertNull($order->waitlist_position);

        $this->assertEquals('confirmed', $reg1->fresh()->status);
        $this->assertNull($reg1->fresh()->waitlist_position);
        
        $this->assertEquals('confirmed', $reg2->fresh()->status);
        $this->assertNull($reg2->fresh()->waitlist_position);

        // Verify ticket decrementing
        $this->assertEquals(8, $ticket->fresh()->available);
    }
}
