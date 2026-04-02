<?php

namespace App\Services\Events;

use App\DTOs\CreateEventDTO;
use App\DTOs\ListEventsFiltersDTO;
use App\DTOs\UpdateEventDTO;
use App\Exceptions\EventException;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Media;
use App\Models\PromoCode;
use App\Services\Contracts\EventServiceContract;
use App\Services\Media\MediaService;
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
     * @param  Authenticatable  $actor  The user creating the event
     * @param  CreateEventDTO  $dto  Event creation data
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
            if (! empty($data['tickets'])) {
                foreach ($data['tickets'] as $ticketData) {
                    $event->tickets()->create($ticketData);
                }
            }

            // Handle Speakers
            if (! empty($data['speakers'])) {
                foreach ($data['speakers'] as $speakerData) {
                    $speaker = $event->speakers()->create($speakerData);
                    if (! empty($speakerData['photo_media_id'])) {
                        $media = Media::find($speakerData['photo_media_id']);
                        if ($media) {
                            $speakerSlug = Str::slug($speaker->name);
                            app(MediaService::class)->promoteToPublic($media, 'speakers', $speakerSlug);
                            $speaker->setPhotoMedia($media->id);
                        }
                    }
                }
            }

            // Handle Media
            if (! empty($data['featured_media_id'])) {
                $media = Media::find($data['featured_media_id']);
                if ($media) {
                    app(MediaService::class)->promoteToPublic($media, 'events', $event->slug);
                    $event->setFeaturedMedia($media->id);
                }
            }
            if (! empty($data['gallery_media_ids'])) {
                foreach ($data['gallery_media_ids'] as $mediaId) {
                    $media = Media::find($mediaId);
                    if ($media) {
                        app(MediaService::class)->promoteToPublic($media, 'events', $event->slug);
                    }
                }
                $event->addGalleryMedia($data['gallery_media_ids']);
            }

            // Handle Promo Codes
            if (! empty($data['promo_codes'])) {
                $providedCodes = [];
                foreach ($data['promo_codes'] as $promoData) {
                    $code = strtoupper($promoData['code']);
                    if (in_array($code, $providedCodes) || PromoCode::withTrashed()->where('code', $code)->exists()) {
                        throw EventException::duplicatePromoCode($code);
                    }
                    $providedCodes[] = $code;
                    $event->promoCodes()->create($promoData);
                }
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

            return $event->load(['organizer', 'category', 'county', 'tickets', 'speakers', 'promoCodes']);
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
     * @param  Authenticatable  $actor  The user updating the event
     * @param  Event  $event  The event to update
     * @param  UpdateEventDTO  $dto  Update data
     * @return Event The updated event
     *
     * @throws EventException If update fails
     */
    public function update(Authenticatable $actor, Event $event, UpdateEventDTO $dto): Event
    {
        try {
            $data = $dto->toArray();

            if (! empty($data)) {
                // Guard: Capacity Reduction
                if (array_key_exists('capacity', $data)) {
                    $registrationService = app(\App\Services\Contracts\EventRegistrationServiceContract::class);
                    $confirmedCount = $registrationService->getGlobalConfirmedCount($event);

                    if ($data['capacity'] !== null && $data['capacity'] < $confirmedCount) {
                        throw EventException::updateFailed("Cannot reduce event capacity effectively below current confirmed count ({$confirmedCount}).");
                    }
                }

                $oldValues = $event->toArray();

                $event->update($data);

                // Smart Ticket Management: When event is set to Free (price = 0)
                if (array_key_exists('price', $data) && $data['price'] == 0 && ($oldValues['price'] ?? 0) > 0) {
                    // 1. Deactivate ALL paid tickets
                    $event->tickets()->where('price', '>', 0)->update(['is_active' => false]);

                    // 2. Ensure at least one FREE ticket exists and is active
                    $hasActiveFreeTicket = $event->tickets()
                        ->where('price', 0)
                        ->where('is_active', true)
                        ->exists();

                    if (!$hasActiveFreeTicket) {
                        // Look for an existing deactivated free ticket to reactivate
                        $deactivatedFreeTicket = $event->tickets()
                            ->where('price', 0)
                            ->where('is_active', false)
                            ->first();

                        if ($deactivatedFreeTicket) {
                            $deactivatedFreeTicket->update(['is_active' => true]);
                        } else {
                            // Create a new General Admission ticket
                            $event->tickets()->create([
                                'name' => 'General Admission',
                                'description' => 'Standard free entry to the event.',
                                'price' => 0,
                                'currency' => $event->currency ?? 'KES',
                                'quantity' => $event->capacity,
                                'available' => $event->capacity,
                                'is_active' => true,
                            ]);
                        }
                    }
                }

                $changes = [];
                foreach ($data as $key => $value) {
                    if (($oldValues[$key] ?? null) !== $value) {
                        $changes[$key] = [
                            'old' => $oldValues[$key] ?? null,
                            'new' => $value,
                        ];
                    }
                }

                if (! empty($changes)) {
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

                // Trigger Promotion if capacity increased or removed (null means unlimited)
                $oldCapacity = $oldValues['capacity'] ?? 0;
                $newCapacity = $data['capacity'] ?? null;
                if (array_key_exists('capacity', $data) && ($newCapacity === null || $newCapacity > $oldCapacity)) {
                    $registrationService = app(\App\Services\Contracts\EventRegistrationServiceContract::class);
                    $registrationService->promoteWaitlistEntries($event);
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
                        $speaker = $event->speakers()->create($speakerData);
                        if (! empty($speakerData['photo_media_id'])) {
                            $media = Media::find($speakerData['photo_media_id']);
                            if ($media) {
                                $speakerSlug = Str::slug($speaker->name);
                                app(MediaService::class)->promoteToPublic($media, 'speakers', $speakerSlug);
                                $speaker->setPhotoMedia($media->id);
                            }
                        }
                    }
                }

                // Handle Promo Codes
                if (isset($data['promo_codes'])) {
                    $incomingCodes = collect($data['promo_codes'])->map(function ($p) {
                        $p['code'] = strtoupper($p['code']);

                        return $p;
                    });

                    $incomingStrings = $incomingCodes->pluck('code')->toArray();
                    $existingCodes = $event->promoCodes()->get();

                    // 1. Handle Removals
                    foreach ($existingCodes as $existing) {
                        if (! in_array($existing->code, $incomingStrings)) {
                            if (($existing->used_count ?? 0) === 0) {
                                $existing->forceDelete();
                            }
                            // If used_count > 0, we preserve it in DB for history/integrity
                        }
                    }

                    // 2. Update or Create
                    $processed = [];
                    foreach ($incomingCodes as $promoData) {
                        $code = $promoData['code'];

                        if (in_array($code, $processed)) {
                            throw EventException::duplicatePromoCode($code);
                        }
                        $processed[] = $code;

                        // Check global uniqueness (excluding this event's existing codes)
                        $conflict = PromoCode::withTrashed()
                            ->where('code', $code)
                            ->where('event_id', '!=', $event->id)
                            ->exists();

                        if ($conflict) {
                            throw EventException::duplicatePromoCode($code);
                        }

                        $existing = $existingCodes->where('code', $code)->first();

                        if ($existing) {
                            $existing->update([
                                'discount_type' => $promoData['discount_type'],
                                'discount_value' => $promoData['discount_value'],
                                'usage_limit' => $promoData['usage_limit'] ?? null,
                                'ticket_id' => $promoData['ticket_id'] ?? null,
                            ]);
                        } else {
                            $event->promoCodes()->create($promoData);
                        }
                    }
                }

                // Tags removed as per request

                // Handle Media
                if (isset($data['featured_media_id'])) {
                    if ($data['featured_media_id']) {
                        $media = Media::find($data['featured_media_id']);
                        if ($media) {
                            app(MediaService::class)->promoteToPublic($media, 'events', $event->slug);
                            $event->setFeaturedMedia($media->id);
                        }
                    } else {
                        $event->setFeaturedMedia(null);
                    }
                }
                if (isset($data['gallery_media_ids'])) {
                    $event->media()->wherePivot('role', 'gallery')->detach();
                    if (! empty($data['gallery_media_ids'])) {
                        foreach ($data['gallery_media_ids'] as $mediaId) {
                            $media = Media::find($mediaId);
                            if ($media) {
                                app(MediaService::class)->promoteToPublic($media, 'events', $event->slug);
                            }
                        }
                    }
                    $event->addGalleryMedia($data['gallery_media_ids']);
                }
            }

            return $event->load(['organizer', 'category', 'county', 'tickets', 'speakers', 'promoCodes']);
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
     * @param  string  $id  The event ID
     * @return Event The event with relationships
     *
     * @throws EventException If event not found
     */
    public function getById(string $id): Event
    {
        try {
            return Event::with([
                'organizer',
                'creator',
                'category',
                'county',
                'tickets',
                'speakers',
                'media',
                'promoCodes',
            ])
                ->withCount(['registrations as registrations_count' => function ($query) {
                    $query->whereNotIn('status', ['cancelled', 'waitlisted']);
                }])
                ->withCount(['registrations as waitlist_count' => function ($query) {
                    $query->where('status', 'waitlisted');
                }])
                ->findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw EventException::notFound($id);
        }
    }

    /**
     * List events with filtering and pagination
     *
     * @param  ListEventsFiltersDTO  $filters  Filtering criteria
     * @param  int  $perPage  Results per page
     * @return LengthAwarePaginator Paginated results
     */
    public function listEvents(ListEventsFiltersDTO $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Event::query()->with(['organizer', 'category', 'county', 'media']);

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

        if ($filters->type) {
            if ($filters->type === 'online') {
                $query->where('is_online', true);
            } elseif ($filters->type === 'in_person') {
                $query->where('is_online', false);
            }
        }

        if ($filters->timeframe === 'upcoming' || $filters->upcoming) {
            $query->where('starts_at', '>', now());
        } elseif ($filters->timeframe === 'past') {
            // Events are "past" if they have already started
            $query->where('starts_at', '<', now());
        }

        if ($filters->start_date) {
            $query->where('starts_at', '>=', $filters->start_date);
        }

        if ($filters->end_date) {
            $query->where('ends_at', '<=', $filters->end_date);
        }

        return $query->withCount(['registrations as registrations_count' => function ($query) {
            $query->whereNotIn('status', ['cancelled', 'waitlisted']);
        }])
            ->orderBy($filters->sort_by ?? 'starts_at', $filters->sort_dir ?? 'asc')
            ->paginate($perPage);
    }

    /**
     * Get event by slug
     *
     * @param  string  $slug  Event slug
     * @return Event The event
     *
     * @throws EventException If not found
     */
    public function getBySlug(string $slug): Event
    {
        try {
            return Event::where('slug', $slug)
                ->with(['organizer', 'registrations', 'category', 'county', 'tickets', 'speakers'])
                ->firstOrFail();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw EventException::notFoundBySlug($slug);
        }
    }

    /**
     * Soft delete an event
     *
     * @param  Authenticatable  $actor  The user deleting
     * @param  Event  $event  The event to delete
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
     * @param  Authenticatable  $actor  The user restoring
     * @param  Event  $event  The event to restore
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
     * @param  Event  $event  The event
     * @return array Statistics
     */
    public function getStatistics(Event $event): array
    {
        $registrationService = app(\App\Services\Contracts\EventRegistrationServiceContract::class);
        $confirmedCount = $registrationService->getGlobalConfirmedCount($event);

        return [
            'event_id' => $event->id,
            'title' => $event->title,
            'total_capacity' => $event->capacity,
            'confirmed_registrations' => $confirmedCount,
            'available_capacity' => max(0, $event->capacity - $confirmedCount),
            'utilization_percentage' => $event->capacity > 0 ? ($confirmedCount / $event->capacity) * 100 : 0,
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
        $query = $event->registrations()->with(['user', 'ticket', 'order.promoCode']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } elseif (! empty($filters['waitlist'])) {
            $query->where('status', 'waitlisted');
        } else {
            $query->whereNotIn('status', ['cancelled', 'waitlisted']);
        }

        if (isset($filters['waitlist']) && $filters['waitlist']) {
            $query->whereNotNull('waitlist_position')->orderBy('waitlist_position');
        } else {
            $query->latest();
        }

        return $query->paginate($perPage);
    }
}
