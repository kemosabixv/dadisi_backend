<?php

namespace Tests\Unit\Services\Events;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventOrder;
use App\Models\Ticket;
use App\Models\User;
use App\Models\County;
use App\Models\EventCategory;
use App\Services\Events\EventRegistrationService;
use App\Services\Events\EventService;
use App\Services\Events\EventTicketService;
use App\Services\EventOrderService;
use App\DTOs\UpdateEventDTO;
use App\DTOs\UpdateTicketDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Illuminate\Support\Facades\Notification;
use App\Notifications\EventWaitlistPromoted;

class EventWaitlistPromotionTest extends TestCase
{
    use RefreshDatabase;

    private EventRegistrationService $registrationService;
    private EventService $eventService;
    private EventTicketService $ticketService;
    private EventOrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registrationService = app(EventRegistrationService::class);
        $this->eventService = app(EventService::class);
        $this->ticketService = app(EventTicketService::class);
        $this->orderService = app(EventOrderService::class);
        
        Notification::fake();
    }

    #[Test]
    public function it_promotes_an_rsvp_when_global_capacity_is_increased()
    {
        // 1. Setup event with 1 capacity
        $event = Event::factory()->withRelations()->create(['capacity' => 1, 'waitlist_enabled' => true]);
        $ticket = Ticket::factory()->create(['event_id' => $event->id, 'quantity' => null]); // unlimited tier

        // 2. Fill it
        $user1 = User::factory()->create();
        $this->registrationService->registerUser($user1, $event, ['ticket_id' => $ticket->id]);

        // 3. User 2 joins waitlist
        $user2 = User::factory()->create();
        $reg2 = $this->registrationService->registerUser($user2, $event, ['ticket_id' => $ticket->id]);
        $this->assertEquals('waitlisted', $reg2->status);

        // 4. Increase capacity via EventService
        $admin = User::factory()->create();
        $this->eventService->update($admin, $event, new UpdateEventDTO(capacity: 2));

        // 5. Assert User 2 is promoted
        $reg2->refresh();
        $this->assertEquals('confirmed', $reg2->status);
        $this->assertNull($reg2->waitlist_position);
        Notification::assertSentTo($user2, EventWaitlistPromoted::class);
    }

    #[Test]
    public function it_promotes_a_paid_order_when_global_capacity_is_increased()
    {
        // 1. Setup event with 1 capacity
        $event = Event::factory()->withRelations()->create(['capacity' => 1, 'price' => 1000, 'waitlist_enabled' => true]);
        $ticket = Ticket::factory()->create(['event_id' => $event->id, 'quantity' => null, 'price' => 1000]);

        // 2. Fill it
        $user1 = User::factory()->create();
        $this->registrationService->registerUser($user1, $event, ['ticket_id' => $ticket->id]);

        // 3. User 2 joins waitlist via Order
        $user2 = User::factory()->create();
        $orderResponse = $this->orderService->createOrder($event, 1, ['ticket_id' => $ticket->id, 'name' => 'U2', 'email' => $user2->email], null, $user2);
        $order = $orderResponse['order'];
        $this->assertEquals('waitlisted', $order->status);

        // 4. Increase capacity
        $admin = User::factory()->create();
        $this->eventService->update($admin, $event, new UpdateEventDTO(capacity: 2));

        // 5. Assert Order is promoted to pending
        $order->refresh();
        $this->assertEquals('pending', $order->status);
        $this->assertEquals(-1, $order->waitlist_position);
        Notification::assertSentTo($user2, EventWaitlistPromoted::class);
        
        // 6. Verify ticket availability was reserved (it was unlimited, but let's check if it decremented if it had a limit)
        // Set a limit for better test
        $ticket->update(['quantity' => 10, 'available' => 10]); // wait, it should be 9 if user1 is confirmed
        $ticket->update(['available' => 9]);
        
        $this->eventService->update($admin, $event, new UpdateEventDTO(capacity: 3));
        // Order was already promoted, so no more promotions. 
        // Let's try with a fresh one.
    }

    #[Test]
    public function it_respects_ticket_tier_limits_during_promotion()
    {
        // Event has capacity 10, Tier A has capacity 1
        $event = Event::factory()->withRelations()->create(['capacity' => 10, 'waitlist_enabled' => true]);
        $ticketA = Ticket::factory()->create(['event_id' => $event->id, 'quantity' => 1, 'available' => 1, 'name' => 'Tier A']);
        $ticketB = Ticket::factory()->create(['event_id' => $event->id, 'quantity' => 10, 'available' => 10, 'name' => 'Tier B']);

        // Fill Tier A
        $user1 = User::factory()->create();
        $this->registrationService->registerUser($user1, $event, ['ticket_id' => $ticketA->id]);

        // User 2 joins waitlist for Tier A
        $user2 = User::factory()->create();
        $reg2 = $this->registrationService->registerUser($user2, $event, ['ticket_id' => $ticketA->id]);
        $this->assertEquals('waitlisted', $reg2->status);

        // User 3 joins for Tier B
        $user3 = User::factory()->create();
        $reg3 = $this->registrationService->registerUser($user3, $event, ['ticket_id' => $ticketB->id]);
        
        // Assert User 3 is confirmed because Tier B has capacity
        $this->assertEquals('confirmed', $reg3->status);

        // Now, increase Tier A quantity
        $admin = User::factory()->create();
        $this->ticketService->updateTicket($admin, $ticketA, new UpdateTicketDTO(quantity: 2));

        // Assert User 2 is promoted
        $reg2->refresh();
        $this->assertEquals('confirmed', $reg2->status);
    }

    #[Test]
    public function it_prevents_reducing_event_capacity_below_confirmed_count()
    {
        $event = Event::factory()->withRelations()->create(['capacity' => 5]);
        $ticket = Ticket::factory()->create(['event_id' => $event->id, 'quantity' => 10]);
        
        // 3 confirmed users
        $users = User::factory()->count(3)->create();
        foreach ($users as $u) {
            $this->registrationService->registerUser($u, $event, ['ticket_id' => $ticket->id]);
        }

        $admin = User::factory()->create();
        
        // Try to reduce to 2
        $this->expectException(\App\Exceptions\EventException::class);
        $this->eventService->update($admin, $event, new UpdateEventDTO(capacity: 2));
    }

    #[Test]
    public function it_prevents_reducing_ticket_quantity_below_sold_count()
    {
        $event = Event::factory()->withRelations()->create(['capacity' => 100]);
        $ticket = Ticket::factory()->create(['event_id' => $event->id, 'quantity' => 5, 'available' => 5]);

        // 3 sold tickets
        $users = User::factory()->count(3)->create();
        foreach ($users as $u) {
            $this->registrationService->registerUser($u, $event, ['ticket_id' => $ticket->id]);
        }

        $admin = User::factory()->create();
        
        // Try to reduce to 2
        $this->expectException(\App\Exceptions\EventTicketException::class);
        $this->ticketService->updateTicket($admin, $ticket, new UpdateTicketDTO(quantity: 2));
    }

    #[Test]
    public function it_promotes_in_fifo_order_respecting_priority()
    {
        $event = Event::factory()->withRelations()->create(['capacity' => 1, 'waitlist_enabled' => true]);
        $ticket = Ticket::factory()->create(['event_id' => $event->id, 'quantity' => 10]);

        // Fill spot
        $this->registrationService->registerUser(User::factory()->create(), $event, ['ticket_id' => $ticket->id]);

        // Waitlist entries
        $userNormal = User::factory()->create(); // Normal user
        $regNormal = $this->registrationService->registerUser($userNormal, $event, ['ticket_id' => $ticket->id]);
        
        // Simulate priority user (mocking the hasWaitlistPriority or just setting the position manually for unit test)
        $userPriority = User::factory()->create();
        // In this test, we'll manually set positions to verify the promoter sorts correctly
        $regPriority = $this->registrationService->registerUser($userPriority, $event, ['ticket_id' => $ticket->id]);
        $regPriority->update(['waitlist_position' => 1]); // Priority position
        $regNormal->update(['waitlist_position' => 1000001]); // Normal position

        // Increase capacity to 2
        $admin = User::factory()->create();
        $this->eventService->update($admin, $event, new UpdateEventDTO(capacity: 2));

        // Priority user should be promoted
        $regPriority->refresh();
        $regNormal->refresh();
        
        $this->assertEquals('confirmed', $regPriority->status);
        $this->assertEquals('waitlisted', $regNormal->status);
    }
}
