<?php

namespace App\Services\Contracts;

use App\Models\Event;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * EventCapacityServiceContract
 *
 * Defines the contract for event capacity management including
 * capacity checks, updates, and reservation handling.
 *
 * Ensures capacity constraints are maintained across registrations.
 */
interface EventCapacityServiceContract
{
    /**
     * Check if event has available capacity
     *
     * @param Event $event The event
     * @param int $slots Number of slots to check (default: 1)
     * @return bool True if capacity available
     */
    public function hasCapacity(Event $event, int $slots = 1): bool;

    /**
     * Get available capacity for an event
     *
     * @param Event $event The event
     * @return int Number of available slots
     */
    public function getAvailableCapacity(Event $event): int;

    /**
     * Get total capacity of an event
     *
     * @param Event $event The event
     * @return int Total capacity
     */
    public function getTotalCapacity(Event $event): int;

    /**
     * Get number of registered attendees
     *
     * @param Event $event The event
     * @return int Number of confirmed registrations
     */
    public function getAttendeeCount(Event $event): int;

    /**
     * Update event capacity
     *
     * @param Authenticatable $actor The user updating capacity
     * @param Event $event The event
     * @param int $newCapacity New capacity value (must be >= current attendees)
     * @return Event The updated event
     *
     * @throws \App\Exceptions\EventException If new capacity < attendees
     */
    public function updateCapacity(Authenticatable $actor, Event $event, int $newCapacity): Event;

    /**
     * Reserve capacity for pending registrations
     *
     * @param Event $event The event
     * @param int $slots Number of slots to reserve
     * @return bool True if reservation successful
     *
     * @throws \App\Exceptions\EventCapacityExceededException If insufficient capacity
     */
    public function reserveCapacity(Event $event, int $slots): bool;

    /**
     * Release reserved capacity
     *
     * @param Event $event The event
     * @param int $slots Number of slots to release
     * @return bool True if release successful
     */
    public function releaseCapacity(Event $event, int $slots): bool;

    /**
     * Check if event is at full capacity
     *
     * @param Event $event The event
     * @return bool True if at capacity
     */
    public function isAtCapacity(Event $event): bool;

    /**
     * Get capacity utilization percentage
     *
     * @param Event $event The event
     * @return float Percentage of capacity used (0-100)
     */
    public function getUtilization(Event $event): float;
}
