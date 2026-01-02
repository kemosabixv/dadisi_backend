<?php

namespace Tests\Unit\Models;

use App\Models\Event;
use App\Models\EventCategory;
use App\Models\County;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * TicketModelTest
 *
 * Tests for Ticket model availability logic:
 * - isAvailable(): checks active, not sold out, not expired
 * - isExpired(): checks available_until deadline
 * - isSoldOut(): checks remaining quantity
 * - scopeAvailable(): query scope for filtering
 */
class TicketModelTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $category = EventCategory::factory()->create();
        $county = County::factory()->create();

        $this->event = Event::factory()->create([
            'category_id' => $category->id,
            'county_id' => $county->id,
        ]);
    }

    // ============ isAvailable Tests ============

    #[Test]
    public function ticket_is_available_when_active_and_not_sold_out_and_not_expired(): void
    {
        $ticket = Ticket::factory()->create([
            'event_id' => $this->event->id,
            'is_active' => true,
            'quantity' => 100,
            'available' => 50,
            'available_until' => now()->addDays(7),
        ]);

        $this->assertTrue($ticket->isAvailable());
    }

    #[Test]
    public function ticket_is_available_with_unlimited_quantity(): void
    {
        $ticket = Ticket::factory()->create([
            'event_id' => $this->event->id,
            'is_active' => true,
            'quantity' => null,
            'available' => null,
            'available_until' => null,
        ]);

        $this->assertTrue($ticket->isAvailable());
    }

    #[Test]
    public function ticket_is_not_available_when_inactive(): void
    {
        $ticket = Ticket::factory()->create([
            'event_id' => $this->event->id,
            'is_active' => false,
            'quantity' => 100,
            'available' => 50,
        ]);

        $this->assertFalse($ticket->isAvailable());
    }

    #[Test]
    public function ticket_is_not_available_when_sold_out(): void
    {
        $ticket = Ticket::factory()->create([
            'event_id' => $this->event->id,
            'is_active' => true,
            'quantity' => 100,
            'available' => 0,
        ]);

        $this->assertFalse($ticket->isAvailable());
    }

    #[Test]
    public function ticket_is_not_available_when_expired(): void
    {
        $ticket = Ticket::factory()->create([
            'event_id' => $this->event->id,
            'is_active' => true,
            'quantity' => 100,
            'available' => 50,
            'available_until' => now()->subDay(),
        ]);

        $this->assertFalse($ticket->isAvailable());
    }

    // ============ isExpired Tests ============

    #[Test]
    public function ticket_is_not_expired_when_available_until_is_null(): void
    {
        $ticket = Ticket::factory()->create([
            'event_id' => $this->event->id,
            'available_until' => null,
        ]);

        $this->assertFalse($ticket->isExpired());
    }

    #[Test]
    public function ticket_is_not_expired_when_available_until_is_in_future(): void
    {
        $ticket = Ticket::factory()->create([
            'event_id' => $this->event->id,
            'available_until' => now()->addDays(7),
        ]);

        $this->assertFalse($ticket->isExpired());
    }

    #[Test]
    public function ticket_is_expired_when_available_until_is_in_past(): void
    {
        $ticket = Ticket::factory()->create([
            'event_id' => $this->event->id,
            'available_until' => now()->subHour(),
        ]);

        $this->assertTrue($ticket->isExpired());
    }

    // ============ isSoldOut Tests ============

    #[Test]
    public function ticket_is_not_sold_out_when_quantity_is_null(): void
    {
        $ticket = Ticket::factory()->create([
            'event_id' => $this->event->id,
            'quantity' => null,
            'available' => null,
        ]);

        $this->assertFalse($ticket->isSoldOut());
    }

    #[Test]
    public function ticket_is_not_sold_out_when_available_greater_than_zero(): void
    {
        $ticket = Ticket::factory()->create([
            'event_id' => $this->event->id,
            'quantity' => 100,
            'available' => 50,
        ]);

        $this->assertFalse($ticket->isSoldOut());
    }

    #[Test]
    public function ticket_is_sold_out_when_available_is_zero(): void
    {
        $ticket = Ticket::factory()->create([
            'event_id' => $this->event->id,
            'quantity' => 100,
            'available' => 0,
        ]);

        $this->assertTrue($ticket->isSoldOut());
    }

    #[Test]
    public function ticket_is_sold_out_when_available_is_negative(): void
    {
        $ticket = Ticket::factory()->create([
            'event_id' => $this->event->id,
            'quantity' => 100,
            'available' => -5, // edge case
        ]);

        $this->assertTrue($ticket->isSoldOut());
    }

    // ============ Scope Tests ============

    #[Test]
    public function scope_available_returns_only_available_tickets(): void
    {
        // Available ticket
        $available = Ticket::factory()->create([
            'event_id' => $this->event->id,
            'is_active' => true,
            'quantity' => 100,
            'available' => 50,
            'available_until' => now()->addDays(7),
        ]);

        // Inactive ticket
        Ticket::factory()->create([
            'event_id' => $this->event->id,
            'is_active' => false,
            'quantity' => 100,
            'available' => 50,
        ]);

        // Sold out ticket
        Ticket::factory()->create([
            'event_id' => $this->event->id,
            'is_active' => true,
            'quantity' => 100,
            'available' => 0,
        ]);

        // Expired ticket
        Ticket::factory()->create([
            'event_id' => $this->event->id,
            'is_active' => true,
            'quantity' => 100,
            'available' => 50,
            'available_until' => now()->subDay(),
        ]);

        $results = Ticket::available()->get();

        $this->assertCount(1, $results);
        $this->assertEquals($available->id, $results->first()->id);
    }

    #[Test]
    public function scope_available_includes_unlimited_tickets(): void
    {
        $unlimited = Ticket::factory()->create([
            'event_id' => $this->event->id,
            'is_active' => true,
            'quantity' => null,
            'available' => null,
            'available_until' => null,
        ]);

        $results = Ticket::available()->get();

        $this->assertCount(1, $results);
        $this->assertEquals($unlimited->id, $results->first()->id);
    }
}
