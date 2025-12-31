<?php

namespace App\Services\Events;

use App\DTOs\CreateEventDTO;
use App\DTOs\ListEventsFiltersDTO;
use App\DTOs\UpdateEventDTO;
use App\Exceptions\EventException;
use App\Models\AuditLog;
use App\Models\Event;
use App\Services\Contracts\EventServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * EventService
 *
 * Implements core event management operations including creation, updates,
 * retrieval, deletion, and restoration. Maintains audit logging for all operations.
 */
class EventService implements EventServiceContract
{
    /**
     * Create a new event
     *
     * @param Authenticatable $actor The user creating the event
     * @param CreateEventDTO $dto Event creation data
     * @return Event The created event
     *
     * @throws EventException If creation fails
     */
    public function create(Authenticatable $actor, CreateEventDTO $dto): Event
    {
        try {
            $data = $dto->toArray();
            $data['slug'] = Str::slug($dto->title);
            $data['organizer_id'] = $actor->getAuthIdentifier();

            $event = Event::create($data);

            // Handle Tickets
            if (!empty($data['tickets'])) {
                foreach ($data['tickets'] as $ticketData) {
                    $event->tickets()->create($ticketData);
                }
            }

            // Handle Speakers
            if (!empty($data['speakers'])) {
                foreach ($data['speakers'] as $speakerData) {
                    $event->speakers()->create($speakerData);
                }
            }

            // Handle Tags
            if (!empty($data['tag_ids'])) {
                $event->tags()->sync($data['tag_ids']);
            }

            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => 'created_event',
                'model_type' => Event::class,
                'model_id' => $event->id,
                'changes' => [
                    'title' => $event->title,
                    'county' => $event->county,
                    'capacity' => $event->capacity,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            Log::info("Event created: {$event->title} (ID: {$event->id})", [
                'actor_id' => $actor->getAuthIdentifier(),
                'event_id' => $event->id,
            ]);

            return $event->load(['organizer', 'category', 'county', 'tickets', 'speakers', 'tags']);
        } catch (\Exception $e) {
            Log::error('Event creation failed', [
                'actor_id' => $actor->getAuthIdentifier(),
                'error' => $e->getMessage(),
            ]);

            throw EventException::creationFailed($e->getMessage());
        }
    }

    /**
     * Update an existing event
     *
     * @param Authenticatable $actor The user updating the event
     * @param Event $event The event to update
     * @param UpdateEventDTO $dto Update data
     * @return Event The updated event
     *
     * @throws EventException If update fails
     */
    public function update(Authenticatable $actor, Event $event, UpdateEventDTO $dto): Event
    {
        try {
            $data = $dto->toArray();

            if (!empty($data)) {
                $oldValues = $event->toArray();

                $event->update($data);

                $changes = [];
                foreach ($data as $key => $value) {
                    if (($oldValues[$key] ?? null) !== $value) {
                        $changes[$key] = [
                            'old' => $oldValues[$key] ?? null,
                            'new' => $value,
                        ];
                    }
                }

                if (!empty($changes)) {
                    AuditLog::create([
                        'user_id' => $actor->getAuthIdentifier(),
                        'action' => 'updated_event',
                        'model_type' => Event::class,
                        'model_id' => $event->id,
                        'changes' => $changes,
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);

                    Log::info("Event updated: {$event->title} (ID: {$event->id})", [
                        'actor_id' => $actor->getAuthIdentifier(),
                        'event_id' => $event->id,
                        'changes' => array_keys($changes),
                    ]);
                }

                // Handle Tickets (simple sync-like logic or just append? Usually update means sync)
                if (isset($data['tickets'])) {
                    $event->tickets()->delete();
                    foreach ($data['tickets'] as $ticketData) {
                        $event->tickets()->create($ticketData);
                    }
                }

                // Handle Speakers
                if (isset($data['speakers'])) {
                    $event->speakers()->delete();
                    foreach ($data['speakers'] as $speakerData) {
                        $event->speakers()->create($speakerData);
                    }
                }

                // Handle Tags
                if (isset($data['tag_ids'])) {
                    $event->tags()->sync($data['tag_ids']);
                }
            }

            return $event->load(['organizer', 'category', 'county', 'tickets', 'speakers', 'tags']);
        } catch (\Exception $e) {
            Log::error('Event update failed', [
                'actor_id' => $actor->getAuthIdentifier(),
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            throw EventException::updateFailed($e->getMessage());
        }
    }

    /**
     * Retrieve an event by ID
     *
     * @param string $id The event ID
     * @return Event The event with relationships
     *
     * @throws EventException If event not found
     */
    public function getById(string $id): Event
    {
        try {
            return Event::with(['organizer', 'registrations'])->findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw EventException::notFound($id);
        }
    }

    /**
     * List events with filtering and pagination
     *
     * @param ListEventsFiltersDTO $filters Filtering criteria
     * @param int $perPage Results per page
     * @return LengthAwarePaginator Paginated results
     */
    public function listEvents(ListEventsFiltersDTO $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Event::query()->with(['organizer', 'category', 'county']);

        if ($filters->status && $filters->status !== 'all') {
            $query->where('status', $filters->status);
        }

        if ($filters->county_id) {
            $query->where('county_id', $filters->county_id);
        }

        if ($filters->category_id) {
            $query->where('category_id', $filters->category_id);
        }

        if ($filters->category) {
            $query->whereHas('category', function ($q) use ($filters) {
                $q->where('slug', $filters->category);
            });
        }

        if ($filters->tag) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->where('slug', $filters->tag);
            });
        }

        if ($filters->search) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', "%{$filters->search}%")
                  ->orWhere('description', 'like', "%{$filters->search}%");
            });
        }

        if ($filters->featured !== null) {
            $query->where('featured', $filters->featured);
        }

        if ($filters->event_type) {
            $query->where('event_type', $filters->event_type);
        }

        if ($filters->timeframe === 'upcoming' || $filters->upcoming) {
            $query->where('starts_at', '>', now());
        } elseif ($filters->timeframe === 'past') {
            $query->where('ends_at', '<', now());
        }

        if ($filters->start_date) {
            $query->where('starts_at', '>=', $filters->start_date);
        }

        if ($filters->end_date) {
            $query->where('ends_at', '<=', $filters->end_date);
        }

        return $query->withCount('registrations')
            ->orderBy($filters->sort_by ?? 'starts_at', $filters->sort_dir ?? 'asc')
            ->paginate($perPage);
    }

    /**
     * Get event by slug
     *
     * @param string $slug Event slug
     * @return Event The event
     *
     * @throws EventException If not found
     */
    public function getBySlug(string $slug): Event
    {
        try {
            return Event::where('slug', $slug)
                ->with(['organizer', 'registrations', 'category', 'county', 'tags', 'tickets', 'speakers'])
                ->firstOrFail();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw EventException::notFoundBySlug($slug);
        }
    }

    /**
     * Soft delete an event
     *
     * @param Authenticatable $actor The user deleting
     * @param Event $event The event to delete
     * @return bool True if successful
     *
     * @throws EventException If deletion fails
     */
    public function delete(Authenticatable $actor, Event $event): bool
    {
        try {
            $event->delete();

            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => 'deleted_event',
                'model_type' => Event::class,
                'model_id' => $event->id,
                'changes' => ['title' => $event->title],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            Log::info("Event deleted: {$event->title} (ID: {$event->id})", [
                'actor_id' => $actor->getAuthIdentifier(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Event deletion failed', [
                'actor_id' => $actor->getAuthIdentifier(),
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            throw EventException::deletionFailed($e->getMessage());
        }
    }

    /**
     * Restore a soft-deleted event
     *
     * @param Authenticatable $actor The user restoring
     * @param Event $event The event to restore
     * @return Event The restored event
     *
     * @throws EventException If restoration fails
     */
    public function restore(Authenticatable $actor, Event $event): Event
    {
        try {
            if ($event->trashed()) {
                $event->restore();
            }

            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => 'restored_event',
                'model_type' => Event::class,
                'model_id' => $event->id,
                'changes' => ['title' => $event->title],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            Log::info("Event restored: {$event->title} (ID: {$event->id})", [
                'actor_id' => $actor->getAuthIdentifier(),
            ]);

            return $event->fresh();
        } catch (\Exception $e) {
            Log::error('Event restoration failed', [
                'actor_id' => $actor->getAuthIdentifier(),
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            throw EventException::restorationFailed($e->getMessage());
        }
    }

    /**
     * Get event statistics
     *
     * @param Event $event The event
     * @return array Statistics
     */
    public function getStatistics(Event $event): array
    {
        $event->loadCount(['registrations as confirmed_count' => function ($query) {
            $query->where('status', 'confirmed');
        }]);

        return [
            'event_id' => $event->id,
            'title' => $event->title,
            'total_capacity' => $event->capacity,
            'confirmed_registrations' => $event->confirmed_count,
            'available_capacity' => max(0, $event->capacity - $event->confirmed_count),
            'utilization_percentage' => $event->capacity > 0 ? ($event->confirmed_count / $event->capacity) * 100 : 0,
            'is_featured' => (bool) $event->featured,
            'status' => $event->status,
            'created_at' => $event->created_at,
            'updated_at' => $event->updated_at,
        ];
    }

    /**
     * Get global event statistics
     *
     * @return array Global statistics
     */
    public function getGlobalStats(): array
    {
        return [
            'total' => Event::count(),
            'published' => Event::where('status', 'published')->count(),
            'upcoming' => Event::where('status', 'published')
                ->where('starts_at', '>', now())
                ->count(),
            'featured' => Event::where('featured', true)->count(),
        ];
    }

    /**
     * Publish an event
     */
    public function publish(Authenticatable $actor, Event $event): Event
    {
        try {
            $event->update([
                'status' => 'published',
                'published_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => 'published_event',
                'model_type' => Event::class,
                'model_id' => $event->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $event->fresh();
        } catch (\Exception $e) {
            throw EventException::updateFailed($e->getMessage());
        }
    }

    /**
     * Cancel an event
     */
    public function cancel(Authenticatable $actor, Event $event): Event
    {
        try {
            $event->update(['status' => 'cancelled']);

            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => 'cancelled_event',
                'model_type' => Event::class,
                'model_id' => $event->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $event->fresh();
        } catch (\Exception $e) {
            throw EventException::updateFailed($e->getMessage());
        }
    }

    /**
     * Suspend an event
     */
    public function suspend(Authenticatable $actor, Event $event): Event
    {
        try {
            $event->update(['status' => 'suspended']);

            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => 'suspended_event',
                'model_type' => Event::class,
                'model_id' => $event->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $event->fresh();
        } catch (\Exception $e) {
            throw EventException::updateFailed($e->getMessage());
        }
    }

    /**
     * Feature an event
     */
    public function feature(Authenticatable $actor, Event $event, ?string $until = null): Event
    {
        try {
            $event->update([
                'featured' => true,
                'featured_until' => $until,
            ]);

            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => 'featured_event',
                'model_type' => Event::class,
                'model_id' => $event->id,
                'changes' => ['until' => $until],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $event->fresh();
        } catch (\Exception $e) {
            throw EventException::updateFailed($e->getMessage());
        }
    }

    /**
     * Unfeature an event
     */
    public function unfeature(Authenticatable $actor, Event $event): Event
    {
        try {
            $event->update([
                'featured' => false,
                'featured_until' => null,
            ]);

            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => 'unfeatured_event',
                'model_type' => Event::class,
                'model_id' => $event->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $event->fresh();
        } catch (\Exception $e) {
            throw EventException::updateFailed($e->getMessage());
        }
    }

    /**
     * List registrations for an event
     */
    public function listRegistrations(Event $event, array $filters = [], int $perPage = 50): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = $event->registrations()->with(['user', 'ticket', 'order']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['waitlist']) && $filters['waitlist']) {
            $query->whereNotNull('waitlist_position')->orderBy('waitlist_position');
        } else {
            $query->latest();
        }

        return $query->paginate($perPage);
    }
}
