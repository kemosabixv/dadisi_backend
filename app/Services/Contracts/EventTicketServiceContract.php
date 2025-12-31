<?php

namespace App\Services\Contracts;

use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;

/**
 * EventTicketServiceContract
 *
 * Contract for managing event ticket tiers (e.g., VIP, Early Bird, General).
 */
interface EventTicketServiceContract
{
    /**
     * Create a new ticket tier for an event
     *
     * @param Authenticatable $actor The user creating the ticket
     * @param Event $event The event the ticket belongs to
     * @param array $data Ticket data
     * @return Ticket The created ticket tier
     */
    public function createTicket(Authenticatable $actor, Event $event, array $data): Ticket;

    /**
     * Update an existing ticket tier
     *
     * @param Authenticatable $actor The user updating the ticket
     * @param Ticket $ticket The ticket tier to update
     * @param array $data Update data
     * @return Ticket The updated ticket tier
     */
    public function updateTicket(Authenticatable $actor, Ticket $ticket, array $data): Ticket;

    /**
     * Delete a ticket tier
     *
     * @param Authenticatable $actor The user deleting the ticket
     * @param Ticket $ticket The ticket tier to delete
     * @return bool
     */
    public function deleteTicket(Authenticatable $actor, Ticket $ticket): bool;

    /**
     * List all ticket tiers for an event
     *
     * @param Event $event The event
     * @param bool $activeOnly Only show active tickets (default: true)
     * @return Collection
     */
    public function listEventTickets(Event $event, bool $activeOnly = true): Collection;

    /**
     * Get a specific ticket by ID
     *
     * @param int $id The ticket ID
     * @return Ticket
     */
    public function getById(int $id): Ticket;

    /**
     * Get statistics for ticket sales/availability
     *
     * @param Ticket $ticket The ticket tier
     * @return array
     */
    public function getTicketStats(Ticket $ticket): array;
}
