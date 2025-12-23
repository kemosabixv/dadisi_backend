<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\EventService;
use App\Services\EventQuotaService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class EventController extends Controller
{
    protected $eventService;
    protected $quotaService;

    public function __construct(EventService $eventService, EventQuotaService $quotaService)
    {
        $this->eventService = $eventService;
        $this->quotaService = $quotaService;
        $this->middleware('auth:sanctum')->except(['index', 'show']);
    }

    /**
     * List Events
     * 
     * @group Events
     * @queryParam category string filter by category slug
     * @queryParam tag string filter by tag slug
     * @queryParam search string search by title/description
     * @queryParam county_id integer filter by county
     * @queryParam type string (online|in_person)
     */
    public function index(Request $request)
    {
        $query = Event::with(['category', 'county', 'tags'])->active();

        if ($request->has('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        if ($request->has('tag')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('slug', $request->tag);
            });
        }

        if ($request->has('county_id')) {
            $query->byCounty($request->county_id);
        }

        if ($request->has('type')) {
            if ($request->type === 'online') {
                $query->where('is_online', true);
            } else {
                $query->where('is_online', false);
            }
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        $events = $query->orderBy('starts_at', 'asc')->paginate($request->get('per_page', 12));

        return EventResource::collection($events);
    }

    /**
     * Get Event Details
     * 
     * @group Events
     * @urlParam slug string required The event slug
     */
    public function show($slug)
    {
        $event = Event::with(['category', 'county', 'tags', 'tickets', 'speakers', 'organizer'])
            ->where('slug', $slug)
            ->firstOrFail();

        return new EventResource($event);
    }

    /**
     * Store Event
     * 
     * @group Events
     * @authenticated
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:event_categories,id',
            'venue' => 'nullable|string|max:255',
            'is_online' => 'required|boolean',
            'online_link' => 'nullable|url|required_if:is_online,true',
            'capacity' => 'nullable|integer|min:1',
            'waitlist_enabled' => 'nullable|boolean',
            'waitlist_capacity' => 'nullable|integer|min:1',
            'county_id' => 'required|exists:counties,id',
            'starts_at' => 'required|date|after:now',
            'ends_at' => 'required|date|after:starts_at',
            'registration_deadline' => 'nullable|date|before:starts_at',
        ]);

        try {
            $event = $this->eventService->createEvent($user, $validated);

            if ($request->hasFile('image')) {
                $this->eventService->uploadImage($event, $request->file('image'));
            }

            return (new EventResource($event->load(['category', 'county'])))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * Update Event
     * 
     * @group Events
     * @authenticated
     */
    public function update(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:event_categories,id',
            'venue' => 'nullable|string|max:255',
            'is_online' => 'nullable|boolean',
            'online_link' => 'nullable|url',
            'capacity' => 'nullable|integer|min:1',
            'waitlist_enabled' => 'nullable|boolean',
            'waitlist_capacity' => 'nullable|integer|min:1',
            'county_id' => 'nullable|exists:counties,id',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'registration_deadline' => 'nullable|date|before:starts_at',
            'status' => 'nullable|string|in:draft,published,cancelled',
        ]);

        $event->update($validated);

        if ($request->hasFile('image')) {
            $this->eventService->uploadImage($event, $request->file('image'));
        }

        return new EventResource($event->load(['category', 'county']));
    }

    /**
     * Delete Event
     * 
     * @group Events
     * @authenticated
     */
    public function destroy(Event $event)
    {
        $this->authorize('delete', $event);
        
        $event->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Get Remaining Quotas
     * 
     * @group Events
     * @authenticated
     */
    public function getQuotas()
    {
        $user = Auth::user();
        
        return response()->json([
            'creation_limit' => $this->quotaService->getFeatureLimit($user, 'event_creation_limit'),
            'creation_usage' => $this->quotaService->getMonthlyCreationUsage($user),
            'creation_remaining' => $this->quotaService->getRemainingCreations($user),
            'participation_limit' => $this->quotaService->getFeatureLimit($user, 'event_participation_limit'),
            'participation_usage' => $this->quotaService->getMonthlyParticipationUsage($user),
            'participation_remaining' => $this->quotaService->getRemainingParticipations($user),
        ]);
    }

    /**
     * List Events for Current User (Organizer)
     * 
     * @group Events
     * @authenticated
     * @queryParam status string Filter by status (draft, published, cancelled). Example: published
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "title": "My Community Workshop",
     *       "status": "published",
     *       "starts_at": "2025-02-15T09:00:00Z"
     *     }
     *   ]
     * }
     */
    public function myEvents(Request $request)
    {
        $user = Auth::user();
        
        $query = Event::with(['category', 'county', 'tags', 'tickets'])
            ->where('organizer_id', $user->id)
            ->orderBy('starts_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $events = $query->paginate($request->get('per_page', 20));

        return EventResource::collection($events);
    }
}
