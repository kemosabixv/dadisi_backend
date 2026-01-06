<?php

namespace App\Http\Controllers\Api\Admin;

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
            $query->where('is_available', $request->boolean('active'));
        }

        $perPage = $request->input('per_page', 15);
        $spaces = $query->with('media')->orderBy('name')->paginate($perPage);

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
     * @bodyParam equipment_list array List of equipment. Example: ["fume_hood", "pcr_machine"]
     * @bodyParam safety_requirements array Required certifications. Example: ["lab_safety_training"]
     * @bodyParam is_available boolean Whether space is available for booking. Example: true
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Lab space created successfully",
     *   "data": {...}
     * }
     */
    public function store(\App\Http\Requests\Api\CreateLabSpaceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_available'] = $validated['is_available'] ?? true;

        $space = LabSpace::create($validated);

        // Handle media attachments
        if (!empty($validated['featured_media_id'])) {
            $space->setFeaturedMedia($validated['featured_media_id']);
        }
        if (!empty($validated['gallery_media_ids'])) {
            $space->addGalleryMedia($validated['gallery_media_ids']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lab space created successfully',
            'data' => $space->load('media'),
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
        $space = LabSpace::with('media')->findOrFail($id);

        $this->authorize('view', $space);

        $space->loadCount('bookings');

        // Add media IDs to response
        $responseData = $space->toArray();
        $responseData['featured_media_id'] = $space->media->firstWhere('pivot.role', 'featured')?->id;
        $responseData['gallery_media_ids'] = $space->media->where('pivot.role', 'gallery')->pluck('id')->values();

        return response()->json([
            'success' => true,
            'data' => $responseData,
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
    public function update(\App\Http\Requests\Api\UpdateLabSpaceRequest $request, int $id): JsonResponse
    {
        $space = LabSpace::findOrFail($id);
        $this->authorize('update', $space);
        $validated = $request->validated();

        // Update slug if name changed
        if (isset($validated['name']) && $validated['name'] !== $space->name) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $space->update($validated);

        // Handle media attachments
        if (array_key_exists('featured_media_id', $validated)) {
            $space->setFeaturedMedia($validated['featured_media_id']);
        }
        if (array_key_exists('gallery_media_ids', $validated)) {
            $space->media()->wherePivot('role', 'gallery')->detach();
            if (!empty($validated['gallery_media_ids'])) {
                $space->addGalleryMedia($validated['gallery_media_ids']);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Lab space updated successfully',
            'data' => $space->fresh()->load('media'),
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
