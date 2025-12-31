<?php

namespace App\Services\Contracts;

use App\Models\Event;
use Illuminate\Support\Collection;

/**
 * EventAttendanceServiceContract
 *
 * Defines contract for managing event attendance and ticket verification.
 */
interface EventAttendanceServiceContract
{
    /**
     * Get attendance statistics for an event
     *
     * @param Event $event
     * @return array
     */
    public function getAttendanceStats(Event $event): array;

    /**
     * Verify a ticket token and check-in the attendee
     *
     * @param Event $event
     * @param string $token
     * @return array Result containing status, message, and attendee details
     */
    public function scanTicket(Event $event, string $token): array;

    /**
     * List all attendees for an event (confirmed and attended)
     *
     * @param Event $event
     * @return Collection
     */
    public function listAttendees(Event $event): Collection;
}
