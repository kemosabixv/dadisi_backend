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
     * @queryParam category string Filter by category slug. Example: biotech-health
     * @queryParam category_id integer Filter by category ID. Example: 1
     * @queryParam tag string Filter by tag slug. Example: free
     * @queryParam search string Search by title/description. Example: workshop
     * @queryParam county_id integer Filter by county. Example: 1
     * @queryParam type string Filter by event type (online|in_person). Example: online
     * @queryParam timeframe string Filter by time (upcoming|past|all). Defaults to all. Example: upcoming
     */
    public function index(Request $request)
    {
        $query = Event::with(['category', 'county', 'tags'])->active();

        // Filter by category slug
        if ($request->has('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Filter by category ID
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('tag')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('slug', $request->tag);
            });
        }

        if ($request->has('county_id')) {
            $query->byCounty($request->county_id);
        }

        // Filter by online/in_person (type param)
        if ($request->has('type')) {
            if ($request->type === 'online') {
                $query->where('is_online', true);
            } else {
                $query->where('is_online', false);
            }
        }

        // Filter by timeframe (upcoming/past)
        if ($request->has('timeframe')) {
            if ($request->timeframe === 'upcoming') {
                $query->where('starts_at', '>', now());
            } elseif ($request->timeframe === 'past') {
                $query->where('starts_at', '<', now());
            }
            // 'all' means no date filter
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
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|in:KES,USD',
            'image_path' => 'nullable|string|max:500',
            // Tags
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:event_tags,id',
            // Tickets
            'tickets' => 'nullable|array',
            'tickets.*.name' => 'required_with:tickets|string|max:255',
            'tickets.*.description' => 'nullable|string',
            'tickets.*.price' => 'required_with:tickets|numeric|min:0',
            'tickets.*.quantity' => 'required_with:tickets|integer|min:1',
            'tickets.*.is_active' => 'nullable|boolean',
            // Speakers
            'speakers' => 'nullable|array',
            'speakers.*.name' => 'required_with:speakers|string|max:255',
            'speakers.*.designation' => 'nullable|string|max:255',
            'speakers.*.company' => 'nullable|string|max:255',
            'speakers.*.bio' => 'nullable|string',
            'speakers.*.is_featured' => 'nullable|boolean',
        ]);

        try {
            $event = $this->eventService->createEvent($user, $validated);

            // Sync tags
            if ($request->has('tag_ids')) {
                $event->tags()->sync($validated['tag_ids'] ?? []);
            }

            // Create tickets
            $ticketsCreated = false;
            if ($request->has('tickets') && !empty($validated['tickets'])) {
                // Validate total ticket quantity vs capacity
                $totalQuantity = array_sum(array_column($validated['tickets'], 'quantity'));
                if ($event->capacity && $totalQuantity > $event->capacity) {
                    return response()->json([
                        'message' => "Total ticket quantity ({$totalQuantity}) exceeds event capacity ({$event->capacity})."
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                foreach ($validated['tickets'] as $ticketData) {
                    $event->tickets()->create($ticketData);
                }
                $ticketsCreated = true;
            }

            // Auto-create default ticket for paid events without user-defined tiers
            // Uses 999999 as a sentinel value for "unlimited" when capacity is not set
            if (!$ticketsCreated && $event->price > 0) {
                $event->tickets()->create([
                    'name' => 'General Admission',
                    'price' => $event->price,
                    'quantity' => $event->capacity ?? 999999,
                    'is_active' => true,
                ]);
            }

            // Auto-create RSVP ticket for free events without user-defined tiers
            // Uses 999999 as a sentinel value for "unlimited" when capacity is not set
            if (!$ticketsCreated && (!$event->price || $event->price == 0)) {
                $event->tickets()->create([
                    'name' => 'RSVP',
                    'price' => 0,
                    'quantity' => $event->capacity ?? 999999,
                    'is_active' => true,
                ]);
            }

            // Create speakers
            if ($request->has('speakers')) {
                foreach ($validated['speakers'] ?? [] as $speakerData) {
                    $event->speakers()->create($speakerData);
                }
            }

            if ($request->hasFile('image')) {
                $this->eventService->uploadImage($event, $request->file('image'));
            }

            return (new EventResource($event->load(['category', 'county', 'tags', 'tickets', 'speakers'])))
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
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|in:KES,USD',
            'image_path' => 'nullable|string|max:500',
            // Tags
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:event_tags,id',
            // Tickets
            'tickets' => 'nullable|array',
            'tickets.*.id' => 'nullable|integer',
            'tickets.*.name' => 'required_with:tickets|string|max:255',
            'tickets.*.description' => 'nullable|string',
            'tickets.*.price' => 'required_with:tickets|numeric|min:0',
            'tickets.*.quantity' => 'required_with:tickets|integer|min:1',
            'tickets.*.is_active' => 'nullable|boolean',
            // Speakers
            'speakers' => 'nullable|array',
            'speakers.*.id' => 'nullable|integer',
            'speakers.*.name' => 'required_with:speakers|string|max:255',
            'speakers.*.designation' => 'nullable|string|max:255',
            'speakers.*.company' => 'nullable|string|max:255',
            'speakers.*.bio' => 'nullable|string',
            'speakers.*.is_featured' => 'nullable|boolean',
        ]);

        // Update base event fields
        $event->update($validated);

        // Sync tags
        if ($request->has('tag_ids')) {
            $event->tags()->sync($validated['tag_ids'] ?? []);
        }

        // Sync tickets (delete and recreate)
        if ($request->has('tickets')) {
            $event->tickets()->delete();
            foreach ($validated['tickets'] ?? [] as $ticketData) {
                unset($ticketData['id']); // Remove id for new creation
                $event->tickets()->create($ticketData);
            }
        }

        // Sync speakers (delete and recreate)
        if ($request->has('speakers')) {
            $event->speakers()->delete();
            foreach ($validated['speakers'] ?? [] as $speakerData) {
                unset($speakerData['id']); // Remove id for new creation
                $event->speakers()->create($speakerData);
            }
        }

        if ($request->hasFile('image')) {
            $this->eventService->uploadImage($event, $request->file('image'));
        }

        return new EventResource($event->load(['category', 'county', 'tags', 'tickets', 'speakers']));
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
            'subscriber_discount' => $this->quotaService->getSubscriberDiscount($user),
            'priority_access' => $this->quotaService->hasPriorityAccess($user),
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
