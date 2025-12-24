<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class AdminEventController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'admin']);
    }

    /**
     * List All Events (Admin/Moderation)
     * 
     * @group Admin Events
     * @queryParam status string Filter by status (draft, pending_approval, published, rejected, cancelled, suspended)
     * @queryParam event_type string Filter by type (organization, user)
     * @queryParam featured boolean Filter by featured status
     * @queryParam organizer_id integer Filter by organizer
     * @queryParam search string Search in title
     * @queryParam upcoming boolean Show only upcoming events
     */
    public function index(Request $request)
    {
        $query = Event::with(['category', 'county', 'organizer', 'creator'])
            ->withCount('registrations');

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by event type
        if ($request->has('event_type') && $request->event_type !== 'all') {
            $query->where('event_type', $request->event_type);
        }

        // Filter by featured
        if ($request->has('featured')) {
            $query->where('featured', filter_var($request->featured, FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by organizer
        if ($request->has('organizer_id')) {
            $query->where('organizer_id', $request->organizer_id);
        }

        // Search
        if ($request->has('search') && !empty($request->search)) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'starts_at');
        $sortDir = $request->get('sort_dir', 'asc');
        
        $allowedSorts = ['title', 'starts_at', 'status', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc');
        } else if ($request->boolean('upcoming')) {
            $query->where('starts_at', '>', now())->orderBy('starts_at', 'asc');
        } else {
            $query->latest();
        }

        $events = $query->paginate($request->get('per_page', 20));

        return EventResource::collection($events);
    }

    /**
     * List Event Registrations
     * 
     * @group Admin Events
     */
    public function registrations(Request $request, Event $event)
    {
        $query = $event->registrations()->with(['user', 'ticket', 'order']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('waitlist')) {
            $query->whereNotNull('waitlist_position')->orderBy('waitlist_position');
        } else {
            $query->whereNull('waitlist_position')->latest();
        }

        return response()->json($query->paginate($request->get('per_page', 50)));
    }

    /**
     * Get Event Statistics
     * 
     * @group Admin Events
     *
     * @response 200 {
     *   "total": 150,
     *   "pending_review": 3,
     *   "published": 142,
     *   "upcoming": 8,
     *   "featured": 2,
     *   "organization_events": 120,
     *   "user_events": 30
     * }
     */
    public function stats()
    {
        return response()->json([
            'total' => Event::count(),
            'pending_review' => Event::where('status', 'pending_approval')->count(),
            'published' => Event::where('status', 'published')->count(),
            'upcoming' => Event::where('status', 'published')->where('starts_at', '>', now())->count(),
            'featured' => Event::where('featured', true)->where(function ($q) {
                $q->whereNull('featured_until')->orWhere('featured_until', '>', now());
            })->count(),
            'organization_events' => Event::where('event_type', 'organization')->count(),
            'user_events' => Event::where('event_type', 'user')->count(),
        ]);
    }

    /**
     * View Any Event Details
     * 
     * @group Admin Events
     */
    public function show(Event $event)
    {
        return new EventResource($event->load(['category', 'county', 'tags', 'tickets', 'speakers', 'organizer', 'creator', 'payouts']));
    }

    /**
     * Create Organization Event
     * 
     * @group Admin Events
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:event_categories,id',
            'county_id' => 'required|exists:counties,id',
            'starts_at' => 'required|date|after:now',
            'ends_at' => 'required|date|after:starts_at',
            'venue' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:1',
            'is_online' => 'required|boolean',
            'online_link' => 'nullable|url|required_if:is_online,true',
            'image_path' => 'nullable|string|max:500',
            'featured' => 'nullable|boolean',
            'featured_until' => 'nullable|date|after:now',
            'organizer_id' => 'nullable|exists:users,id',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|in:KES,USD',
            'waitlist_enabled' => 'nullable|boolean',
            'waitlist_capacity' => 'nullable|integer|min:1',
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

        $validated['created_by'] = auth()->id();
        $validated['organizer_id'] = $validated['organizer_id'] ?? auth()->id();
        $validated['event_type'] = $request->input('organizer_id') ? 'user' : 'organization';
        $validated['status'] = 'published';
        $validated['slug'] = Str::slug($validated['title'] . '-' . uniqid());

        $event = Event::create($validated);

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

        return (new EventResource($event->load(['category', 'county', 'organizer', 'creator', 'tags', 'tickets', 'speakers'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update Event
     * 
     * @group Admin Events
     */
    public function update(Request $request, Event $event)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:event_categories,id',
            'county_id' => 'nullable|exists:counties,id',
            'venue' => 'nullable|string|max:255',
            'is_online' => 'nullable|boolean',
            'online_link' => 'nullable|url',
            'capacity' => 'nullable|integer|min:1',
            'waitlist_enabled' => 'nullable|boolean',
            'waitlist_capacity' => 'nullable|integer|min:1',
            'image_path' => 'nullable|string|max:500',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'registration_deadline' => 'nullable|date|before:starts_at',
            'status' => 'nullable|string|in:draft,published,pending_approval,cancelled,suspended',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|in:KES,USD',
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

        return new EventResource($event->load(['category', 'county', 'organizer', 'creator', 'tags', 'tickets', 'speakers']));
    }

    /**
     * Approve Pending Event
     * 
     * @group Admin Events
     */
    public function approve(Event $event)
    {
        if ($event->status !== 'pending_approval') {
            return response()->json(['message' => 'Event is not pending approval.'], Response::HTTP_BAD_REQUEST);
        }

        $event->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return response()->json(['message' => 'Event approved and published successfully.']);
    }

    /**
     * Reject Pending Event
     * 
     * @group Admin Events
     * @bodyParam reason string The reason for rejection
     */
    public function reject(Request $request, Event $event)
    {
        $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        if ($event->status !== 'pending_approval') {
            return response()->json(['message' => 'Event is not pending approval.'], Response::HTTP_BAD_REQUEST);
        }

        $event->update(['status' => 'rejected']);

        // TODO: Send notification to organizer with rejection reason

        return response()->json(['message' => 'Event rejected.']);
    }

    /**
     * Publish Event
     * 
     * @group Admin Events
     */
    public function publish(Event $event)
    {
        $event->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return response()->json(['message' => 'Event published successfully.']);
    }

    /**
     * Cancel Event
     * 
     * @group Admin Events
     */
    public function cancel(Event $event)
    {
        $event->update(['status' => 'cancelled']);
        return response()->json(['message' => 'Event cancelled.']);
    }

    /**
     * Suspend/Hide Event (for violations)
     * 
     * @group Admin Events
     */
    public function suspend(Event $event)
    {
        $event->update(['status' => 'suspended']);
        return response()->json(['message' => 'Event suspended successfully.']);
    }

    /**
     * Feature Event
     * 
     * @group Admin Events
     */
    public function feature(Request $request, Event $event)
    {
        $validated = $request->validate([
            'until' => 'nullable|date|after:now',
        ]);

        $event->update([
            'featured' => true,
            'featured_until' => $validated['until'] ?? null,
        ]);

        return response()->json(['message' => 'Event featured successfully.']);
    }

    /**
     * Unfeature Event
     * 
     * @group Admin Events
     */
    public function unfeature(Event $event)
    {
        $event->update([
            'featured' => false,
            'featured_until' => null,
        ]);

        return response()->json(['message' => 'Event unfeatured successfully.']);
    }

    /**
     * Delete Event (Moderation)
     * 
     * @group Admin Events
     */
    public function destroy(Event $event)
    {
        $event->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
