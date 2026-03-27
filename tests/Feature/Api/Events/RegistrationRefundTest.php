<?php

namespace Tests\Feature\Api\Events;

use App\Models\County;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\EventOrder;
use App\Models\EventRegistration;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Refund;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationRefundTest extends TestCase
{
    use RefreshDatabase;

    protected bool $shouldSeedRoles = true;

    protected function setUp(): void
    {
        parent::setUp();
        
        if (!\Spatie\Permission\Models\Permission::where('name', 'manage_refunds')->exists()) {
            \Spatie\Permission\Models\Permission::create(['name' => 'manage_refunds', 'guard_name' => 'web']);
        }

        // Create an admin who should receive the notification
        $admin = User::factory()->create(['username' => 'staff_admin']);
        $admin->givePermissionTo('manage_refunds');
    }

    public function test_refund_request_via_token_immediately_cancels_registration_and_order(): void
    {
        // 1. Setup Event at capacity
        $category = EventCategory::factory()->create();
        $county = County::factory()->create();
        $event = Event::factory()->create([
            'category_id' => $category->id,
            'county_id' => $county->id,
            'capacity' => 1,
            'waitlist_enabled' => true,
            'price' => 1000,
            'status' => 'published',
            'starts_at' => now()->addDays(10),
        ]);

        $ticket = $event->tickets()->create([
            'name' => 'Standard',
            'price' => 1000,
            'quantity' => 1,
            'available' => 0, // Mark as sold out
        ]);

        // 2. Confirmed user (User 1)
        $user1 = User::factory()->create();
        $order1 = EventOrder::create([
            'event_id' => $event->id,
            'ticket_id' => $ticket->id,
            'user_id' => $user1->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'total_amount' => 1000,
            'status' => 'paid',
            'reference' => 'ORD-1',
            'currency' => 'KES'
        ]);

        Payment::create([
            'payable_type' => 'event_order',
            'payable_id' => $order1->id,
            'user_id' => $user1->id,
            'amount' => 1000,
            'currency' => 'KES',
            'status' => 'paid',
            'gateway' => 'pesapal',
            'reference' => 'PAY-1',
            'order_reference' => 'ORD-1',
            'external_reference' => 'EXT-1'
        ]);

        $reg1 = EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $user1->id,
            'order_id' => $order1->id,
            'ticket_id' => $ticket->id,
            'status' => 'confirmed',
            'qr_code_token' => 'TOKEN-TEST',
            'confirmation_code' => 'CONF-1'
        ]);

        // 3. Waitlisted user (User 2)
        $user2 = User::factory()->create();
        $order2 = EventOrder::create([
            'event_id' => $event->id,
            'ticket_id' => $ticket->id,
            'user_id' => $user2->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'total_amount' => 1000,
            'status' => 'waitlisted',
            'waitlist_position' => 1000000,
            'reference' => 'ORD-2',
            'currency' => 'KES'
        ]);
        $reg2 = EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $user2->id,
            'order_id' => $order2->id,
            'ticket_id' => $ticket->id,
            'status' => 'waitlisted',
            'waitlist_position' => 1000000,
            'confirmation_code' => 'CONF-2'
        ]);

        // 4. Request refund via token for User 1
        $response = $this->postJson("/api/registrations/token/TOKEN-TEST/refund", [
            'reason' => 'Change of plans',
            'customer_notes' => 'Please refund to M-Pesa'
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        // 5. Verify User 1 is cancelled
        $reg1->refresh();
        $order1->refresh();
        $this->assertEquals('cancelled', $reg1->status);
        $this->assertEquals('cancelled', $order1->status);

        // 6. Verify Refund record is created
        $this->assertDatabaseHas('refunds', [
            'refundable_id' => $order1->id,
            'refundable_type' => 'event_order',
            'status' => Refund::STATUS_PENDING,
            'customer_notes' => 'Please refund to M-Pesa'
        ]);

        // 7. Verify User 2 is promoted (implicitly by checking order status)
        // Note: EventRegistrationService::promoteWaitlistEntries updates statuses
        $order2->refresh();
        $reg2->refresh();
        $this->assertEquals('pending', $order2->status);
        $this->assertEquals('pending', $reg2->status);
        $this->assertEquals(-1, $order2->waitlist_position);
        $this->assertEquals(-1, $reg2->waitlist_position);
    }
}
