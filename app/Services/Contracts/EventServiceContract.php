<?php

namespace App\Services\Contracts;

use App\DTOs\CreateEventDTO;
use App\DTOs\ListEventsFiltersDTO;
use App\DTOs\UpdateEventDTO;
use App\Models\Event;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * EventServiceContract
 *
 * Defines the contract for core event management operations including
 * creation, updates, retrieval, deletion, and restoration of events.
 *
 * All methods must handle audit logging and maintain data consistency.
 */
interface EventServiceContract
{
    /**
     * Create a new event
     *
     * @param Authenticatable $actor The user creating the event
     * @param CreateEventDTO $dto Event creation data
     * @return Event The created event
     *
     * @throws \App\Exceptions\EventException If creation fails
     */
    public function create(Authenticatable $actor, CreateEventDTO $dto): Event;

    /**
     * Update an existing event
     *
     * @param Authenticatable $actor The user updating the event
     * @param Event $event The event to update
     * @param UpdateEventDTO $dto Update data (all fields nullable)
     * @return Event The updated event
     *
     * @throws \App\Exceptions\EventException If update fails
     */
    public function update(Authenticatable $actor, Event $event, UpdateEventDTO $dto): Event;

    /**
     * Retrieve an event by ID
     *
     * @param string $id The event ID
     * @return Event The event with all relationships loaded
     *
     * @throws \App\Exceptions\EventException If event not found
     */
    public function getById(string $id): Event;

    /**
     * List events with filtering and pagination
     *
     * @param ListEventsFiltersDTO $filters Filtering criteria
     * @param int $perPage Results per page (default: 15)
     * @return LengthAwarePaginator Paginated event results
     */
    public function listEvents(ListEventsFiltersDTO $filters, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get event by slug (for public viewing)
     *
     * @param string $slug Event slug
     * @return Event The event
     *
     * @throws \App\Exceptions\EventException If event not found
     */
    public function getBySlug(string $slug): Event;

    /**
     * Soft delete an event
     *
     * @param Authenticatable $actor The user deleting the event
     * @param Event $event The event to delete
     * @return bool True if successful
     *
     * @throws \App\Exceptions\EventException If deletion fails
     */
    public function delete(Authenticatable $actor, Event $event): bool;

    /**
     * Restore a soft-deleted event
     *
     * @param Authenticatable $actor The user restoring the event
     * @param Event $event The event to restore
     * @return Event The restored event
     *
     * @throws \App\Exceptions\EventException If restoration fails
     */
    public function restore(Authenticatable $actor, Event $event): Event;

    /**
     * Get event statistics (attendee count, registration count, etc)
     *
     * @param Event $event The event
     * @return array Event statistics
     */
    public function getStatistics(Event $event): array;

    /**
     * Get global event statistics
     *
     * @return array Global statistics
     */
    public function getGlobalStats(): array;

    /**
     * Publish an event
     *
     * @param Authenticatable $actor
     * @param Event $event
     * @return Event
     */
    public function publish(Authenticatable $actor, Event $event): Event;

    /**
     * Cancel an event
     *
     * @param Authenticatable $actor
     * @param Event $event
     * @return Event
     */
    public function cancel(Authenticatable $actor, Event $event): Event;

    /**
     * Suspend an event
     *
     * @param Authenticatable $actor
     * @param Event $event
     * @return Event
     */
    public function suspend(Authenticatable $actor, Event $event): Event;

    /**
     * Feature an event
     *
     * @param Authenticatable $actor
     * @param Event $event
     * @param string|null $until
     * @return Event
     */
    public function feature(Authenticatable $actor, Event $event, ?string $until = null): Event;

    /**
     * Unfeature an event
     *
     * @param Authenticatable $actor
     * @param Event $event
     * @return Event
     */
    public function unfeature(Authenticatable $actor, Event $event): Event;

    /**
     * List registrations for an event
     *
     * @param Event $event
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function listRegistrations(Event $event, array $filters = [], int $perPage = 50): \Illuminate\Pagination\LengthAwarePaginator;
}
