<?php

namespace App\Http\Controllers\Api\Admin;

use App\DTOs\CreateEventDTO;
use App\DTOs\ListEventsFiltersDTO;
use App\DTOs\UpdateEventDTO;
use App\Exceptions\EventException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListEventsRequest;
use App\Http\Requests\Api\StoreEventRequest;
use App\Http\Requests\Api\UpdateEventRequest;
use App\Http\Requests\Api\FeatureEventRequest;
use App\Http\Requests\Api\ListRegistrationsRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\Contracts\EventServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Admin Events
 * @groupDescription Administrative endpoints for managing events, including moderation, publishing, and feature management.
 */
class AdminEventController extends Controller
{
    public function __construct(
        private EventServiceContract $eventService
    ) {
        $this->middleware(['auth:sanctum', 'admin']);
    }

    /**
     * List All Events (Admin/Moderation)
     * 
     * @group Admin Events
     * @authenticated
     * 
     * @queryParam status string Filter by status (published, cancelled, suspended). Example: published
     * @queryParam event_type string Filter by type. Example: workshop
     * @queryParam featured boolean Filter by featured status. Example: true
     * @queryParam organizer_id integer Filter by organizer. Example: 1
     * @queryParam search string Search in title. Example: Tech
     * @queryParam upcoming boolean Show only upcoming events. Example: true
     * @queryParam sort_by string Sort field (title, starts_at, status, created_at). Example: starts_at
     * @queryParam sort_dir string Sort direction (asc, desc). Example: desc
     * @queryParam per_page integer Results per page. Example: 15
     * 
     * @response 200 {
     *   "data": [{"id": 1, "title": "Tech Conference", "status": "published"}],
     *   "meta": {"current_page": 1, "total": 50}
     * }
     */
    public function index(ListEventsRequest $request)
    {
        try {
            $filters = ListEventsFiltersDTO::fromRequest($request->validated());
            $events = $this->eventService->listEvents($filters, $filters->per_page);

            return EventResource::collection($events);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('Failed to list events', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve events'], 500);
        }
    }

    /**
     * List Event Registrations
     * 
     * @group Admin Events
     * @authenticated
     * 
     * @urlParam event required The event ID. Example: 1
     * @queryParam status string Filter by status (confirmed, attended, cancelled, waitlisted). Example: confirmed
     * @queryParam waitlist boolean Show waitlisted registrations only. Example: false
     * @queryParam per_page integer Results per page. Example: 50
     * 
     * @response 200 {
     *   "data": [{"id": 1, "user": {"username": "john"}, "status": "confirmed"}],
     *   "meta": {"total": 100}
     * }
     */
    public function registrations(ListRegistrationsRequest $request, Event $event): JsonResponse
    {
        try {
            $filters = $request->validated();
            $registrations = $this->eventService->listRegistrations(
                $event,
                $filters,
                (int)($filters['per_page'] ?? 50)
            );

            return response()->json($registrations);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('Failed to list registrations', ['error' => $e->getMessage(), 'event_id' => $event->id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve registrations'], 500);
        }
    }

    /**
     * Get Event Statistics
     * 
     * Returns aggregate statistics for all events.
     * 
     * @group Admin Events
     * @authenticated
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "total": 150,
     *     "published": 100,
     *     "upcoming": 25,
     *     "featured": 10
     *   }
     * }
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->eventService->getGlobalStats();
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('Failed to get event stats', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve statistics'], 500);
        }
    }

    /**
     * View Any Event Details
     * 
     * @group Admin Events
     * @authenticated
     * 
     * @urlParam event required The event ID. Example: 1
     * 
     * @response 200 {
     *   "data": {"id": 1, "title": "Tech Conference", "organizer": {"username": "admin"}}
     * }
     */
    public function show(Event $event)
    {
        try {
            $event = $this->eventService->getById($event->id);
            return new EventResource($event);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('Failed to get event details', ['error' => $e->getMessage(), 'event_id' => $event->id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve event'], 500);
        }
    }

    /**
     * Create Organization Event
     * 
     * Creates a new event as an admin/organizer.
     * 
     * @group Admin Events
     * @authenticated
     * 
     * @response 201 {
     *   "data": {"id": 1, "title": "New Event", "status": "draft"}
     * }
     */
    public function store(StoreEventRequest $request)
    {
        try {
            $validated = $request->validated();
            $dto = CreateEventDTO::fromRequest($validated);
            $event = $this->eventService->create(auth()->user(), $dto);

            return (new EventResource($event))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (EventException $e) {
            Log::error('Failed to create event', ['error' => $e->getMessage()]);
            return $e->render();
        } catch (\Exception $e) {
            Log::error('Failed to create event', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create event'], 500);
        }
    }

    /**
     * Update Event
     * 
     * @group Admin Events
     * @authenticated
     * 
     * @urlParam event required The event ID. Example: 1
     * 
     * @response 200 {
     *   "data": {"id": 1, "title": "Updated Event"}
     * }
     */
    public function update(UpdateEventRequest $request, Event $event)
    {
        try {
            $validated = $request->validated();
            $dto = UpdateEventDTO::fromRequest($validated);
            $event = $this->eventService->update(auth()->user(), $event, $dto);

            return (new EventResource($event))
                ->additional(['success' => true, 'message' => 'Event updated successfully']);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('Failed to update event', ['error' => $e->getMessage(), 'event_id' => $event->id]);
            return response()->json(['success' => false, 'message' => 'Failed to update event'], 500);
        }
    }

    /**
     * Publish Event
     * 
     * Makes an event publicly visible.
     * 
     * @group Admin Events
     * @authenticated
     * 
     * @urlParam event required The event ID. Example: 1
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {"id": 1, "status": "published"},
     *   "message": "Event published successfully"
     * }
     */
    public function publish(Event $event): JsonResponse
    {
        try {
            $event = $this->eventService->publish(auth()->user(), $event);

            return response()->json([
                'success' => true,
                'data' => new EventResource($event),
                'message' => 'Event published successfully',
            ]);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('Failed to publish event', ['error' => $e->getMessage(), 'event_id' => $event->id]);
            return response()->json(['success' => false, 'message' => 'Failed to publish event'], 500);
        }
    }

    /**
     * Cancel Event
     * 
     * Cancels an event and notifies registered attendees.
     * 
     * @group Admin Events
     * @authenticated
     * 
     * @urlParam event required The event ID. Example: 1
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Event cancelled successfully"
     * }
     */
    public function cancel(Event $event): JsonResponse
    {
        try {
            $this->eventService->cancel(auth()->user(), $event);

            return response()->json([
                'success' => true,
                'message' => 'Event cancelled successfully',
            ]);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('Failed to cancel event', ['error' => $e->getMessage(), 'event_id' => $event->id]);
            return response()->json(['success' => false, 'message' => 'Failed to cancel event'], 500);
        }
    }

    /**
     * Suspend/Hide Event (for violations)
     * 
     * Suspends an event for policy violations. Event becomes hidden from public view.
     * 
     * @group Admin Events
     * @authenticated
     * 
     * @urlParam event required The event ID. Example: 1
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Event suspended successfully"
     * }
     */
    public function suspend(Event $event): JsonResponse
    {
        try {
            $this->eventService->suspend(auth()->user(), $event);

            return response()->json([
                'success' => true,
                'message' => 'Event suspended successfully',
            ]);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('Failed to suspend event', ['error' => $e->getMessage(), 'event_id' => $event->id]);
            return response()->json(['success' => false, 'message' => 'Failed to suspend event'], 500);
        }
    }

    /**
     * Feature Event
     * 
     * Marks an event as featured for promotional purposes.
     * 
     * @group Admin Events
     * @authenticated
     * 
     * @urlParam event required The event ID. Example: 1
     * @bodyParam until string nullable Date until when to feature (must be after now). Example: 2025-12-31
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {"id": 1, "is_featured": true},
     *   "message": "Event featured successfully"
     * }
     */
    public function feature(FeatureEventRequest $request, Event $event): JsonResponse
    {
        try {
            $validated = $request->validated();
            $event = $this->eventService->feature(auth()->user(), $event, $validated['until'] ?? null);

            return response()->json([
                'success' => true,
                'data' => new EventResource($event),
                'message' => 'Event featured successfully',
            ]);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('Failed to feature event', ['error' => $e->getMessage(), 'event_id' => $event->id]);
            return response()->json(['success' => false, 'message' => 'Failed to feature event'], 500);
        }
    }

    /**
     * Unfeature Event
     * 
     * Removes an event from featured status.
     * 
     * @group Admin Events
     * @authenticated
     * 
     * @urlParam event required The event ID. Example: 1
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Event unfeatured successfully"
     * }
     */
    public function unfeature(Event $event): JsonResponse
    {
        try {
            $this->eventService->unfeature(auth()->user(), $event);

            return response()->json([
                'success' => true,
                'message' => 'Event unfeatured successfully',
            ]);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('Failed to unfeature event', ['error' => $e->getMessage(), 'event_id' => $event->id]);
            return response()->json(['success' => false, 'message' => 'Failed to unfeature event'], 500);
        }
    }

    /**
     * Delete Event (Moderation)
     * 
     * Soft deletes an event.
     * 
     * @group Admin Events
     * @authenticated
     * 
     * @urlParam event required The event ID. Example: 1
     * 
     * @response 204 {}
     */
    public function destroy(Event $event): JsonResponse
    {
        try {
            $this->eventService->delete(auth()->user(), $event);

            return response()->json(null, Response::HTTP_NO_CONTENT);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('Failed to delete event', ['error' => $e->getMessage(), 'event_id' => $event->id]);
            return response()->json(['success' => false, 'message' => 'Failed to delete event'], 500);
        }
    }
}
