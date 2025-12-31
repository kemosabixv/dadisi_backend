<?php

namespace App\Services\Contracts;

use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;

/**
 * EventRegistrationServiceContract
 *
 * Defines the contract for event registration workflows including
 * user registration, cancellation, and registration management.
 *
 * Handles capacity constraints and registration state management.
 */
interface EventRegistrationServiceContract
{
    /**
     * Register a user for an event
     *
     * @param Authenticatable $user The user registering
     * @param Event $event The event to register for
     * @param array $data Additional registration data (optional)
     * @return EventRegistration The registration record
     *
     * @throws \App\Exceptions\EventException If registration fails
     * @throws \App\Exceptions\EventCapacityExceededException If event is at capacity
     */
    public function registerUser(Authenticatable $user, Event $event, array $data = []): EventRegistration;

    /**
     * Cancel a user's event registration
     *
     * @param Authenticatable $user The user
     * @param Event $event The event
     * @param string|null $reason Cancellation reason
     * @return bool True if successful
     *
     * @throws \App\Exceptions\EventException If cancellation fails
     */
    public function cancelRegistration(Authenticatable $user, Event $event, ?string $reason = null): bool;

    /**
     * Get registration for a user and event
     *
     * @param Authenticatable $user The user
     * @param Event $event The event
     * @return EventRegistration|null The registration or null
     */
    public function getRegistration(Authenticatable $user, Event $event): ?EventRegistration;

    /**
     * Check if user is registered for an event
     *
     * @param Authenticatable $user The user
     * @param Event $event The event
     * @return bool True if registered
     */
    public function isRegistered(Authenticatable $user, Event $event): bool;

    /**
     * Get all registrations for an event
     *
     * @param Event $event The event
     * @return Collection Event registrations
     */
    public function getEventRegistrations(Event $event): Collection;

    /**
     * Get all events a user is registered for
     *
     * @param Authenticatable $user The user
     * @return Collection User's registrations
     */
    public function getUserRegistrations(Authenticatable $user): Collection;

    /**
     * Get count of confirmed registrations for an event
     *
     * @param Event $event The event
     * @return int Number of confirmed registrations
     */
    public function getConfirmedCount(Event $event): int;

    /**
     * Bulk register multiple users for an event
     *
     * @param Event $event The event
     * @param array $userIds User IDs to register (max 50)
     * @param Authenticatable|null $actor The user performing the action
     * @return int Number of successful registrations
     *
     * @throws \App\Exceptions\EventException If limit exceeded
     * @throws \App\Exceptions\EventCapacityExceededException If would exceed capacity
     */
    public function bulkRegister(Event $event, array $userIds, ?Authenticatable $actor = null): int;

    /**
     * Bulk cancel registrations for an event
     *
     * @param Event $event The event
     * @param array $userIds User IDs to cancel (max 50)
     * @param Authenticatable|null $actor The user performing the action
     * @return int Number of cancellations
     *
     * @throws \App\Exceptions\EventException If limit exceeded
     */
    public function bulkCancel(Event $event, array $userIds, ?Authenticatable $actor = null): int;

    /**
     * Check in a user using a QR code token
     *
     * @param string $qrCodeToken
     * @return EventRegistration
     *
     * @throws \App\Exceptions\EventException
     */
    public function checkIn(string $qrCodeToken): EventRegistration;
}
