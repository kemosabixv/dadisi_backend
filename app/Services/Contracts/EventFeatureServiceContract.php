<?php

namespace App\Services\Contracts;

use App\Models\Event;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * EventFeatureServiceContract
 *
 * Defines the contract for event feature management including
 * featured status, event promotion, and featured event retrieval.
 *
 * Handles featured event rankings and promotion workflows.
 */
interface EventFeatureServiceContract
{
    /**
     * Mark an event as featured
     *
     * @param Authenticatable $actor The user featuring the event
     * @param Event $event The event to feature
     * @param int $priority Feature priority (1-10, lower = higher priority)
     * @param string|null $reason Reason for featuring (optional)
     * @return Event The updated event
     *
     * @throws \App\Exceptions\EventException If feature fails
     */
    public function featureEvent(Authenticatable $actor, Event $event, int $priority = 5, ?string $reason = null): Event;

    /**
     * Unfeature an event
     *
     * @param Authenticatable $actor The user unfeaturing the event
     * @param Event $event The event to unfeature
     * @return Event The updated event
     *
     * @throws \App\Exceptions\EventException If unfeature fails
     */
    public function unfeatureEvent(Authenticatable $actor, Event $event): Event;

    /**
     * Check if an event is featured
     *
     * @param Event $event The event
     * @return bool True if event is featured
     */
    public function isFeatured(Event $event): bool;

    /**
     * Get all featured events
     *
     * @param string|null $county Filter by county (optional)
     * @param int $limit Maximum number of results
     * @return \Illuminate\Database\Eloquent\Collection Featured events ordered by priority
     */
    public function getFeaturedEvents(?string $county = null, int $limit = 10): \Illuminate\Database\Eloquent\Collection;

    /**
     * Update feature priority
     *
     * @param Authenticatable $actor The user updating priority
     * @param Event $event The event
     * @param int $priority New priority (1-10)
     * @return Event The updated event
     *
     * @throws \App\Exceptions\EventException If priority invalid or event not featured
     */
    public function updatePriority(Authenticatable $actor, Event $event, int $priority): Event;

    /**
     * Get feature info for an event
     *
     * @param Event $event The event
     * @return array|null Feature information or null if not featured
     */
    public function getFeatureInfo(Event $event): ?array;

    /**
     * Bulk feature multiple events
     *
     * @param Authenticatable $actor The user featuring events
     * @param array $eventIds Event IDs to feature (max 20)
     * @param int $priority Starting priority (default: 5)
     * @return int Number of featured events
     *
     * @throws \App\Exceptions\EventException If limit exceeded
     */
    public function bulkFeature(Authenticatable $actor, array $eventIds, int $priority = 5): int;

    /**
     * Bulk unfeature events
     *
     * @param Authenticatable $actor The user unfeaturing events
     * @param array $eventIds Event IDs to unfeature (max 20)
     * @return int Number of unfeatured events
     *
     * @throws \App\Exceptions\EventException If limit exceeded
     */
    public function bulkUnfeature(Authenticatable $actor, array $eventIds): int;

    /**
     * Get events expiring from featured status (within days)
     *
     * @param int $withinDays Number of days to look ahead (default: 7)
     * @return \Illuminate\Database\Eloquent\Collection Events expiring soon
     */
    public function getExpiringFeaturedEvents(int $withinDays = 7): \Illuminate\Database\Eloquent\Collection;
}
