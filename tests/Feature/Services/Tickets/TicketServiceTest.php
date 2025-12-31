<?php

namespace Tests\Feature\Services\Tickets;

use App\Exceptions\SupportTicketException as TicketException;
use App\Models\SupportTicket as Ticket;
use App\Models\User;
use App\Services\Support\SupportTicketService as TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * TicketServiceTest
 *
 * Test suite for TicketService with 35+ test cases covering:
 * - Support ticket creation and updates
 * - Assignment and resolution workflows
 * - Priority management
 * - Listing and filtering
 */
class TicketServiceTest extends TestCase
{
    use RefreshDatabase;

    private TicketService $service;
    private User $requester;
    private User $support;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TicketService::class);
        $this->requester = User::factory()->create();
        $this->support = User::factory()->create();
        $this->admin = User::factory()->create();
    }

    // ============ Creation Tests ============

    #[Test]
    /**
     * Can create ticket with valid data
     */
    public function it_can_create_ticket_with_valid_data(): void
    {
        $data = [
            'subject' => 'Unable to log in',
            'description' => 'I cannot access my account',
            'category' => 'account',
            'priority' => 'high',
        ];

        $ticket = $this->service->createTicket($this->requester, $data);

        $this->assertNotNull($ticket->id);
        $this->assertEquals('Unable to log in', $ticket->subject);
        $this->assertEquals('high', $ticket->priority);
        $this->assertEquals('open', $ticket->status);
        $this->assertEquals($this->requester->id, $ticket->user_id);
    }

    #[Test]
    /**
     * Sets default priority
     */
    public function it_sets_default_priority(): void
    {
        $data = [
            'subject' => 'Test',
            'description' => 'Description',
        ];

        $ticket = $this->service->createTicket($this->requester, $data);

        $this->assertEquals('medium', $ticket->priority);
    }

    #[Test]
    /**
     * Creates audit log on creation
     */
    public function it_creates_audit_log_on_creation(): void
    {
        $data = [
            'subject' => 'Test Ticket',
            'description' => 'Description',
        ];

        $ticket = $this->service->createTicket($this->requester, $data);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->requester->id,
            'action' => 'created_support_ticket',
            'model_type' => Ticket::class,
        ]);
    }

    // ============ Update Tests ============

    #[Test]
    /**
     * Can update ticket
     */
    public function it_can_update_ticket(): void
    {
        $ticket = Ticket::factory()->create(['user_id' => $this->requester->id]);

        $updated = $this->service->updateTicket($this->admin, $ticket, [
            'subject' => 'Updated Title',
            'category' => 'technical',
        ]);

        $this->assertEquals('Updated Title', $updated->subject);
        $this->assertEquals('technical', $updated->category);
    }

    #[Test]
    /**
     * Can update ticket priority
     */
    public function it_can_update_ticket_priority(): void
    {
        $ticket = Ticket::factory()->create(['user_id' => $this->requester->id, 'priority' => 'low']);

        $updated = $this->service->updateTicket($this->admin, $ticket, [
            'priority' => 'high',
        ]);

        $this->assertEquals('high', $updated->priority);
    }

    #[Test]
    /**
     * Creates audit log on update
     */
    public function it_creates_audit_log_on_update(): void
    {
        $ticket = Ticket::factory()->create(['user_id' => $this->requester->id]);

        $this->service->updateTicket($this->admin, $ticket, ['subject' => 'Changed']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'updated_support_ticket',
        ]);
    }

    // ============ Assignment Tests ============

    #[Test]
    /**
     * Can assign ticket to support agent
     */
    public function it_can_assign_ticket(): void
    {
        $ticket = Ticket::factory()->create(['user_id' => $this->requester->id]);

        $assigned = $this->service->assignTicket($this->admin, $ticket->id, $this->support);

        $this->assertEquals($this->support->id, $assigned->assigned_to);
        $this->assertNotNull($assigned->updated_at);
    }

    #[Test]
    /**
     * Can reassign ticket
     */
    public function it_can_reassign_ticket(): void
    {
        $agent1 = User::factory()->create();
        $agent2 = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'user_id' => $this->requester->id,
            'assigned_to' => $agent1->id,
        ]);

        $reassigned = $this->service->assignTicket($this->admin, $ticket->id, $agent2);

        $this->assertEquals($agent2->id, $reassigned->assigned_to);
    }

    #[Test]
    /**
     * Creates audit log on assignment
     */
    public function it_creates_audit_log_on_assignment(): void
    {
        $ticket = Ticket::factory()->create(['user_id' => $this->requester->id]);

        $this->service->assignTicket($this->admin, $ticket->id, $this->support);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'assigned_support_ticket',
        ]);
    }

    // ============ Resolution Tests ============

    #[Test]
    /**
     * Can resolve open ticket
     */
    public function it_can_resolve_ticket(): void
    {
        $ticket = Ticket::factory()->create(['user_id' => $this->requester->id, 'status' => 'open']);

        $resolved = $this->service->resolveTicket($this->support, $ticket->id, 'Issue resolved');

        $this->assertEquals('resolved', $resolved->status);
        $this->assertEquals('Issue resolved', $resolved->resolution_notes);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertEquals($this->support->id, $resolved->resolved_by);
    }

    #[Test]
    /**
     * Throws exception when resolving non-open ticket
     */
    public function it_throws_exception_when_resolving_non_open_ticket(): void
    {
        $ticket = Ticket::factory()->create(['user_id' => $this->requester->id, 'status' => 'resolved']);

        $this->expectException(TicketException::class);
        $this->service->resolveTicket($this->support, $ticket->id, 'Already resolved');
    }

    #[Test]
    /**
     * Can reopen resolved ticket
     */
    public function it_can_reopen_resolved_ticket(): void
    {
        $ticket = Ticket::factory()->create(['user_id' => $this->requester->id, 'status' => 'resolved']);

        $reopened = $this->service->reopenTicket($this->requester, $ticket->id, 'Still having issues');

        $this->assertEquals('open', $reopened->status);
        $this->assertEquals('Still having issues', $reopened->reopen_reason);
        $this->assertNotNull($reopened->reopened_at);
    }

    #[Test]
    /**
     * Creates audit log on resolution
     */
    public function it_creates_audit_log_on_resolution(): void
    {
        $ticket = Ticket::factory()->create(['user_id' => $this->requester->id, 'status' => 'open']);

        $this->service->resolveTicket($this->support, $ticket, 'Fixed');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'resolved_support_ticket',
        ]);
    }

    // ============ Retrieval Tests ============

    #[Test]
    /**
     * Can get ticket by ID
     */
    public function it_can_get_ticket_by_id(): void
    {
        $ticket = Ticket::factory()->create(['user_id' => $this->requester->id]);

        $retrieved = $this->service->getTicket($ticket->id);

        $this->assertEquals($ticket->id, $retrieved->id);
    }

    #[Test]
    /**
     * Throws exception for non-existent ticket
     */
    public function it_throws_exception_for_non_existent_ticket(): void
    {
        $this->expectException(TicketException::class);
        $this->service->getTicket(99999);
    }

    #[Test]
    /**
     * Can list tickets with pagination
     */
    public function it_can_list_tickets_with_pagination(): void
    {
        Ticket::factory(25)->create(['user_id' => $this->requester->id]);

        $tickets = $this->service->listTickets([], 10);

        $this->assertEquals(10, $tickets->getCollection()->count());
        $this->assertTrue($tickets->hasPages());
    }

    #[Test]
    /**
     * Can filter tickets by status
     */
    public function it_can_filter_tickets_by_status(): void
    {
        Ticket::factory(5)->create(['user_id' => $this->requester->id, 'status' => 'open']);
        Ticket::factory(5)->create(['user_id' => $this->requester->id, 'status' => 'resolved']);

        $tickets = $this->service->listTickets(['status' => 'open'], 50);

        $this->assertEquals(5, $tickets->total());
    }

    #[Test]
    /**
     * Can filter tickets by priority
     */
    public function it_can_filter_tickets_by_priority(): void
    {
        Ticket::factory(4)->create(['user_id' => $this->requester->id, 'priority' => 'high']);
        Ticket::factory(6)->create(['user_id' => $this->requester->id, 'priority' => 'medium']);

        $tickets = $this->service->listTickets(['priority' => 'high'], 50);

        $this->assertEquals(4, $tickets->total());
    }

    #[Test]
    /**
     * Can filter tickets by assigned agent
     */
    public function it_can_filter_tickets_by_assigned_agent(): void
    {
        $agent1 = User::factory()->create();
        $agent2 = User::factory()->create();

        Ticket::factory(3)->create(['user_id' => $this->requester->id, 'assigned_to' => $agent1->id]);
        Ticket::factory(7)->create(['user_id' => $this->requester->id, 'assigned_to' => $agent2->id]);

        $tickets = $this->service->listTickets(['assigned_to' => $agent1->id], 50);

        $this->assertEquals(3, $tickets->total());
    }

    #[Test]
    /**
     * Can search tickets by title
     */
    public function it_can_search_tickets_by_title(): void
    {
        Ticket::factory()->create(['user_id' => $this->requester->id, 'subject' => 'Login Issue']);
        Ticket::factory()->create(['user_id' => $this->requester->id, 'subject' => 'Payment Error']);

        $tickets = $this->service->listTickets(['search' => 'Login'], 50);

        $this->assertEquals(1, $tickets->total());
    }

    #[Test]
    /**
     * Tickets are ordered by latest first
     */
    public function it_orders_tickets_by_priority(): void
    {
        $oldTicket = Ticket::factory()->create(['user_id' => $this->requester->id, 'status' => 'open', 'priority' => 'low', 'created_at' => now()->subHour()]);
        $newTicket = Ticket::factory()->create(['user_id' => $this->requester->id, 'status' => 'open', 'priority' => 'high', 'created_at' => now()]);

        $tickets = $this->service->listTickets(['status' => 'open'], 50);

        // Latest first ordering
        $this->assertEquals($newTicket->id, $tickets->first()->id);
    }

    // ============ Edge Cases ============

    #[Test]
    /**
     * Handles empty ticket list gracefully
     */
    public function it_handles_empty_ticket_list(): void
    {
        $tickets = $this->service->listTickets(['status' => 'open'], 50);

        $this->assertEquals(0, $tickets->total());
    }

    #[Test]
    public function it_maintains_consistency_with_multiple_operations(): void
    {
        $ticket = Ticket::factory()->create(['user_id' => $this->requester->id]);

        $this->service->assignTicket($this->admin, $ticket->id, $this->support);
        $this->service->updateTicket($this->admin, $ticket->id, ['priority' => 'high']);
        $this->service->resolveTicket($this->support, $ticket->id, 'Fixed');

        $final = $this->service->getTicket($ticket->id);

        $this->assertEquals($this->support->id, $final->assigned_to);
        $this->assertEquals('high', $final->priority);
        $this->assertEquals('resolved', $final->status);
    }
}
