<?php

namespace Tests\Feature\Api;

use App\Models\Donation;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\EventOrder;
use App\Models\Ticket;
use App\Models\User;
use App\Models\County;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundRequestTest extends TestCase
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

        // Seed required system setting for cancellation deadline
        \App\Models\SystemSetting::updateOrCreate(
            ['key' => 'event_cancellation_deadline_days'],
            [
                'value' => '30', // Far enough in the future
                'group' => 'events',
                'type' => 'integer'
            ]
        );
    }

    public function test_public_refund_request_finds_event_order_by_reference(): void
    {
        $category = EventCategory::factory()->create();
        $county = County::factory()->create();
        $event = Event::factory()->create([
            'category_id' => $category->id,
            'county_id' => $county->id,
            'starts_at' => now()->addDays(40),
        ]);
        $ticket = $event->tickets()->create([
            'name' => 'Test Ticket',
            'price' => 1000,
            'quantity' => 10,
        ]);

        $order = EventOrder::factory()->paid()->create([
            'event_id' => $event->id,
            'ticket_id' => $ticket->id,
            'guest_email' => 'guest@example.com',
            'quantity' => 1,
            'total_amount' => 1000,
            'currency' => 'KES',
            'reference' => 'ORD-REF-123'
        ]);

        $order->registrations()->create([
            'event_id' => $event->id,
            'ticket_id' => $ticket->id,
            'order_id' => $order->id,
            'confirmation_code' => 'CONF-123',
            'user_id' => null,
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.com',
            'status' => 'confirmed',
        ]);

        \App\Models\Payment::factory()->paid()->create([
            'payable_type' => 'event_order',
            'payable_id' => $order->id,
            'amount' => 1000,
            'currency' => 'KES',
            'order_reference' => 'ORD-REF-123',
            'external_reference' => 'ORD-REF-123'
        ]);

        $response = $this->postJson('/api/refunds', [
            'order_reference' => 'ORD-REF-123',
            'email' => 'guest@example.com',
            'reason' => 'Test reason',
            'customer_notes' => 'Some notes'
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        // Check if Refund record is created
        $this->assertDatabaseHas('refunds', [
            'refundable_id' => $order->id,
            'refundable_type' => 'event_order',
        ]);
        
        // Ensure order is now cancelled
        $this->assertEquals('cancelled', $order->fresh()->status);
    }

    public function test_public_refund_request_returns_error_for_donations(): void
    {
        $county = County::factory()->create();
        $donation = Donation::create([
            'donor_name' => 'Donor',
            'donor_email' => 'donor@example.com',
            'county_id' => $county->id,
            'amount' => 5000,
            'currency' => 'KES',
            'status' => 'paid',
            'reference' => 'DON-REF-456'
        ]);

        $response = $this->postJson('/api/refunds', [
            'order_reference' => 'DON-REF-456',
            'email' => 'donor@example.com',
            'reason' => 'Donation refund test',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Donations are non-refundable. Please contact support for exceptional cases.'
            ]);
    }
}
