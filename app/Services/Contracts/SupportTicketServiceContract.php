<?php

namespace App\Services\Contracts;

use App\Models\SupportTicket;
use App\Models\SupportTicketResponse;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * SupportTicketServiceContract
 *
 * Contract for managing support tickets and customer service interactions.
 */
interface SupportTicketServiceContract
{
    /**
     * Create a new support ticket
     */
    public function createTicket(Authenticatable $actor, array $data): SupportTicket;

    /**
     * List support tickets with filters
     */
    public function listTickets(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get ticket detail with responses
     */
    public function getTicketDetail(int $id): SupportTicket;

    /**
     * Get ticket by ID (alias for tests)
     */
    public function getTicket(int $id): SupportTicket;

    /**
     * Update an existing ticket
     */
    public function updateTicket(Authenticatable $actor, SupportTicket|int $ticket, array $data): SupportTicket;

    /**
     * Add a response to a ticket
     */
    public function addResponse(Authenticatable $actor, int $ticketId, string $message, array $attachments = [], bool $isInternal = false): SupportTicketResponse;

    /**
     * Assign a ticket to an admin/staff
     */
    public function assignTicket(Authenticatable $actor, int $ticketId, int $assigneeId): SupportTicket;

    /**
     * Update ticket status
     */
    public function updateStatus(Authenticatable $actor, int $ticketId, string $status): SupportTicket;

    /**
     * Resolve a ticket
     */
    public function resolveTicket(Authenticatable $actor, SupportTicket|int $ticket, ?string $notes = null): SupportTicket;

    /**
     * Reopen a resolved ticket
     */
    public function reopenTicket(Authenticatable $actor, SupportTicket|int $ticket, string $reason): SupportTicket;

    /**
     * Close a ticket
     */
    public function closeTicket(Authenticatable $actor, int $ticketId): SupportTicket;
}
