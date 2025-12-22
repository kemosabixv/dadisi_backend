<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LabSpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @group Admin - Lab Spaces
 *
 * Admin endpoints for managing lab spaces.
 * @authenticated
 */
class AdminLabSpaceController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:manage_lab_spaces')->except(['index', 'show']);
    }

    /**
     * List all lab spaces (paginated).
     *
     * @queryParam type string Filter by space type. Example: wet_lab
     * @queryParam active boolean Filter by active status. Example: true
     * @queryParam per_page integer Items per page. Default: 15. Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "data": {...},
     *   "meta": {"current_page": 1, "last_page": 1, "per_page": 15, "total": 4}
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LabSpace::class);

        $query = LabSpace::query();

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $perPage = $request->input('per_page', 15);
        $spaces = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $spaces->items(),
            'meta' => [
                'current_page' => $spaces->currentPage(),
                'last_page' => $spaces->lastPage(),
                'per_page' => $spaces->perPage(),
                'total' => $spaces->total(),
            ],
        ]);
    }

    /**
     * Create a new lab space.
     *
     * @bodyParam name string required The name of the lab space. Example: Wet Lab
     * @bodyParam type string required The type (wet_lab, dry_lab, greenhouse, mobile_lab). Example: wet_lab
     * @bodyParam description string Description of the lab space. Example: Fully equipped wet laboratory...
     * @bodyParam capacity integer Maximum capacity. Default: 4. Example: 6
     * @bodyParam amenities array List of amenities. Example: ["fume_hood", "pcr_machine"]
     * @bodyParam safety_requirements array Required certifications. Example: ["lab_safety_training"]
     * @bodyParam hourly_rate number Hourly rate (for future paid access). Example: 0
     * @bodyParam is_active boolean Whether space is available for booking. Example: true
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Lab space created successfully",
     *   "data": {...}
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['wet_lab', 'dry_lab', 'greenhouse', 'mobile_lab'])],
            'description' => ['nullable', 'string'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'image_path' => ['nullable', 'string'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string'],
            'safety_requirements' => ['nullable', 'array'],
            'safety_requirements.*' => ['string'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $space = LabSpace::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Lab space created successfully',
            'data' => $space,
        ], 201);
    }

    /**
     * Get lab space details.
     *
     * @urlParam id integer required The lab space ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {...}
     * }
     */
    public function show(int $id): JsonResponse
    {
        $space = LabSpace::findOrFail($id);

        $this->authorize('view', $space);

        $space->loadCount(['bookings', 'maintenanceBlocks']);

        return response()->json([
            'success' => true,
            'data' => $space,
        ]);
    }

    /**
     * Update a lab space.
     *
     * @urlParam id integer required The lab space ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Lab space updated successfully",
     *   "data": {...}
     * }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $space = LabSpace::findOrFail($id);

        $this->authorize('update', $space);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(['wet_lab', 'dry_lab', 'greenhouse', 'mobile_lab'])],
            'description' => ['nullable', 'string'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'image_path' => ['nullable', 'string'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string'],
            'safety_requirements' => ['nullable', 'array'],
            'safety_requirements.*' => ['string'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Update slug if name changed
        if (isset($validated['name']) && $validated['name'] !== $space->name) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $space->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Lab space updated successfully',
            'data' => $space->fresh(),
        ]);
    }

    /**
     * Delete a lab space (soft delete).
     *
     * @urlParam id integer required The lab space ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Lab space deleted successfully"
     * }
     */
    public function destroy(int $id): JsonResponse
    {
        $space = LabSpace::findOrFail($id);

        $this->authorize('delete', $space);

        $space->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lab space deleted successfully',
        ]);
    }
}
