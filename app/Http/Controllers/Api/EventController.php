<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;

/**
 * Public Event API
 * 
 * Events are now managed exclusively by admin/staff.
 * This controller only provides public read access.
 */
class EventController extends Controller
{
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
        $event = Event::with(['category', 'county', 'tags', 'tickets', 'speakers', 'creator'])
            ->where('slug', $slug)
            ->firstOrFail();

        return new EventResource($event);
    }
}
