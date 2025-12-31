<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ListEventsFiltersDTO;
use App\Exceptions\EventException;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Services\Contracts\EventServiceContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public Event API
 * 
 * Events are now managed exclusively by admin/staff.
 * This controller only provides public read access.
 */
class EventController extends Controller
{
    public function __construct(
        private EventServiceContract $eventService
    ) {}

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
        try {
            $filters = ListEventsFiltersDTO::fromRequest($request->all());
            $events = $this->eventService->listEvents($filters, $filters->per_page);

            return EventResource::collection($events);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('Failed to list events', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve events',
            ], 500);
        }
    }

    /**
     * Get Event Details
     * 
     * @group Events
     * @urlParam slug string required The event slug
     */
    public function show($slug)
    {
        try {
            $event = $this->eventService->getBySlug($slug);
            return new EventResource($event);
        } catch (EventException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('Failed to retrieve event', ['error' => $e->getMessage(), 'slug' => $slug]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve event',
            ], 500);
        }
    }
}
