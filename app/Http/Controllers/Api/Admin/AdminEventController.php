<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
     */
    public function index(Request $request)
    {
        $query = Event::with(['category', 'county', 'organizer', 'creator']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('featured')) {
            $query->where('featured', (bool) $request->featured);
        }

        if ($request->has('organizer_id')) {
            $query->where('organizer_id', $request->organizer_id);
        }

        $events = $query->latest()->paginate($request->get('per_page', 20));

        return EventResource::collection($events);
    }

    /**
     * View Any Event Details
     * 
     * @group Admin Events
     */
    public function show(Event $event)
    {
        return new EventResource($event->load(['category', 'county', 'tags', 'tickets', 'speakers', 'organizer', 'payouts']));
    }

    /**
     * Create Organization Event
     * 
     * @group Admin Events
     */
    public function store(Request $request)
    {
        // Admin can create events on behalf of the organization
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:event_categories,id',
            'county_id' => 'required|exists:counties,id',
            'starts_at' => 'required|date|after:now',
            'ends_at' => 'required|date|after:starts_at',
            'capacity' => 'nullable|integer',
            'is_online' => 'required|boolean',
            'featured' => 'nullable|boolean',
            'featured_until' => 'nullable|date|after:now',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['organizer_id'] = auth()->id(); // Org events use the creator (admin) as organizer
        $validated['status'] = 'published';

        $event = Event::create($validated);

        return new EventResource($event->load(['category', 'county']));
    }

    /**
     * Suspend/Hide Event
     * 
     * @group Admin Events
     */
    public function suspend(Event $event)
    {
        $event->update(['status' => 'cancelled']);
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
