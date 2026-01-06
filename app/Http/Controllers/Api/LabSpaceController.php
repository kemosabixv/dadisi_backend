<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LabSpace;
use App\Services\Contracts\LabBookingServiceContract;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Lab Spaces
 *
 * Public endpoints for browsing lab spaces and checking availability.
 */
class LabSpaceController extends Controller
{
    public function __construct(private LabBookingServiceContract $bookingService) {}

    /**
     * List all active lab spaces.
     *
     * @queryParam type string Filter by space type (wet_lab, dry_lab, greenhouse, mobile_lab). Example: wet_lab
     * @queryParam search string Search by name or description. Example: biology
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Nairobi Biotech Hub",
     *       "slug": "nairobi-biotech-hub",
     *       "type": "wet_lab",
     *       "type_name": "Wet Lab",
     *       "description": "State-of-the-art biotechnology facility with advanced PCR and gene sequencing equipment.",
     *       "capacity": 10,
     *       "amenities": ["fume_hood", "biosafety_cabinet", "nanodrop"],
     *       "safety_requirements": ["lab_safety_training", "ppe_required"],
     *       "is_active": true
     *     }
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = LabSpace::active();

            // Filter by type
            if ($request->has('type')) {
                $query->byType($request->type);
            }

            // Filter by county
            if ($request->has('county')) {
                $query->forCounty($request->county);
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $spaces = $query->with('media')->orderBy('name')->get();

            // Add computed attributes
            $spaces->each(function ($space) {
                $space->append(['image_url', 'type_name', 'gallery_media', 'is_active', 'amenities']);
            });

            return response()->json([
                'success' => true,
                'data' => $spaces,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to retrieve lab spaces', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve lab spaces'], 500);
        }
    }

    /**
     * Get lab space details.
     *
     * @urlParam slug string required The slug of the lab space. Example: wet-lab
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "name": "Nairobi Central Wet Lab",
     *     "slug": "nairobi-central-wet-lab",
     *     "type": "wet_lab",
     *     "type_name": "Wet Lab",
     *     "description": "Primary biotechnology facility with PCR and electrophoresis capability.",
     *     "capacity": 8,
     *     "amenities": ["fume_hood", "pcr_machine", "centrifuge"],
     *     "safety_requirements": ["lab_safety_training", "biosafety_level_1"],
     *     "is_active": true
     *   }
     * }
     * @response 404 {"success": false, "message": "Lab space not found"}
     */
    public function show(string $slug): JsonResponse
    {
        try {
            $space = LabSpace::with('media')->where('slug', $slug)->active()->first();

            if (!$space) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lab space not found',
                ], 404);
            }

            $space->append(['image_url', 'type_name', 'gallery_media', 'is_active', 'amenities']);

            return response()->json([
                'success' => true,
                'data' => $space,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to retrieve lab space', ['error' => $e->getMessage(), 'slug' => $slug]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve lab space'], 500);
        }
    }

    /**
     * Get availability calendar for a lab space.
     *
     * Returns bookings and maintenance blocks for calendar display.
     *
     * @urlParam slug string required The slug of the lab space. Example: wet-lab
     * @queryParam start string Start date in ISO 8601 format. Defaults to start of current month. Example: 2024-01-01T00:00:00Z
     * @queryParam end string End date in ISO 8601 format. Defaults to end of current month. Example: 2024-01-31T23:59:59Z
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "space": {...},
     *     "events": [
     *       {
     *         "id": "booking_1",
     *         "title": "Booked",
     *         "start": "2024-01-15T09:00:00Z",
     *         "end": "2024-01-15T12:00:00Z",
     *         "type": "booking",
     *         "status": "approved",
     *         "user": "john_doe"
     *       },
     *       {
     *         "id": "maintenance_1",
     *         "title": "Equipment Servicing",
     *         "start": "2024-01-20T08:00:00Z",
     *         "end": "2024-01-20T18:00:00Z",
     *         "type": "maintenance",
     *         "reason": "Annual PCR machine calibration"
     *       }
     *     ]
     *   }
     * }
     */
    public function availability(Request $request, string $slug): JsonResponse
    {
        try {
            $space = LabSpace::where('slug', $slug)->active()->first();

            if (!$space) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lab space not found',
                ], 404);
            }

            // Default to current month if not specified
            $start = $request->has('start') 
                ? Carbon::parse($request->start) 
                : Carbon::now()->startOfMonth();
            
            $end = $request->has('end') 
                ? Carbon::parse($request->end) 
                : Carbon::now()->endOfMonth();

            $events = $this->bookingService->getAvailabilityCalendar($space, $start, $end);

            return response()->json([
                'success' => true,
                'data' => [
                    'space' => $space->only(['id', 'name', 'slug', 'type', 'capacity']),
                    'events' => $events,
                ],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to retrieve lab space availability', ['error' => $e->getMessage(), 'slug' => $slug]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve availability'], 500);
        }
    }
}
