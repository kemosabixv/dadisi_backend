<?php

namespace App\Services\Events;

use App\Exceptions\EventException;
use App\Models\AuditLog;
use App\Models\Event;
use App\Services\Contracts\EventFeatureServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * EventFeatureService
 *
 * Simplified Manages event featured status using a boolean column on the events table.
 */
class EventFeatureService implements EventFeatureServiceContract
{
    /**
     * Feature an event
     */
    public function featureEvent(Authenticatable $actor, Event $event, int $priority = 5, ?string $reason = null): Event
    {
        try {
            $event->update([
                'featured' => true,
                'featured_until' => now()->addDays(30), // Default 30 days
            ]);

            AuditLog::create([
                'actor_id' => $actor->getAuthIdentifier(),
                'action' => 'featured_event',
                'model' => Event::class,
                'model_id' => $event->id,
                'changes' => ['featured' => true],
            ]);

            Log::info("Event featured", [
                'actor_id' => $actor->getAuthIdentifier(),
                'event_id' => $event->id,
            ]);

            return $event->fresh();
        } catch (\Exception $e) {
            Log::error('Event featuring failed', [
                'actor_id' => $actor->getAuthIdentifier(),
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            throw EventException::featuringFailed($e->getMessage());
        }
    }

    /**
     * Unfeature an event
     */
    public function unfeatureEvent(Authenticatable $actor, Event $event): Event
    {
        try {
            $event->update([
                'featured' => false,
                'featured_until' => null,
            ]);

            AuditLog::create([
                'actor_id' => $actor->getAuthIdentifier(),
                'action' => 'unfeatured_event',
                'model' => Event::class,
                'model_id' => $event->id,
                'changes' => ['featured' => false],
            ]);

            Log::info("Event unfeatured", [
                'actor_id' => $actor->getAuthIdentifier(),
                'event_id' => $event->id,
            ]);

            return $event->fresh();
        } catch (\Exception $e) {
            Log::error('Event unfeaturing failed', [
                'actor_id' => $actor->getAuthIdentifier(),
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            throw EventException::unfeaturingFailed($e->getMessage());
        }
    }

    /**
     * Check if event is featured
     */
    public function isFeatured(Event $event): bool
    {
        return (bool) $event->featured;
    }

    /**
     * Get featured events
     */
    public function getFeaturedEvents(?string $county = null, int $limit = 10): Collection
    {
        return Event::query()
            ->where('featured', true)
            ->when($county, function ($query) use ($county) {
                return $query->whereHas('county', function ($q) use ($county) {
                    $q->where('name', $county);
                })->orWhere('county_id', $county);
            })
            ->orderBy('starts_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Update feature priority (Not used in simplified version)
     */
    public function updatePriority(Authenticatable $actor, Event $event, int $priority): Event
    {
        // No-op for now as we don't have a priority column on the simplified version
        return $event;
    }

    /**
     * Get feature info
     */
    public function getFeatureInfo(Event $event): ?array
    {
        if (!$event->featured) {
            return null;
        }

        return [
            'featured' => true,
            'featured_until' => $event->featured_until,
        ];
    }

    /**
     * Bulk feature events
     */
    public function bulkFeature(Authenticatable $actor, array $eventIds, int $priority = 5): int
    {
        if (count($eventIds) > 20) {
            throw EventException::bulkOperationLimitExceeded(20);
        }

        return Event::whereIn('id', $eventIds)->update([
            'featured' => true,
            'featured_until' => now()->addDays(30),
        ]);
    }

    /**
     * Bulk unfeature events
     */
    public function bulkUnfeature(Authenticatable $actor, array $eventIds): int
    {
        if (count($eventIds) > 20) {
            throw EventException::bulkOperationLimitExceeded(20);
        }

        return Event::whereIn('id', $eventIds)->update([
            'featured' => false,
            'featured_until' => null,
        ]);
    }

    /**
     * Get expiring featured events
     */
    public function getExpiringFeaturedEvents(int $withinDays = 7): Collection
    {
        return Event::query()
            ->where('featured', true)
            ->where('featured_until', '<=', now()->addDays($withinDays))
            ->get();
    }
}
