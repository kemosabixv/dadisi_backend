<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LabMaintenanceBlock;
use App\Models\LabSpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin - Lab Maintenance
 *
 * APIs for managing lab space maintenance blocks.
 * Maintenance blocks prevent bookings during scheduled maintenance periods.
 */
class AdminLabMaintenanceController extends Controller
{
    /**
     * List all maintenance blocks.
     *
     * @authenticated
     * @queryParam lab_space_id integer Filter by lab space. Example: 1
     * @queryParam upcoming boolean Show only upcoming blocks. Example: true
     * @queryParam per_page integer Items per page. Default: 15. Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "data": [...]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage_lab_spaces');

        $query = LabMaintenanceBlock::with(['labSpace:id,name,slug', 'creator:id,username']);

        if ($request->has('lab_space_id')) {
            $query->where('lab_space_id', $request->lab_space_id);
        }

        if ($request->boolean('upcoming')) {
            $query->upcoming();
        }

        $perPage = $request->input('per_page', 15);
        $blocks = $query->orderBy('starts_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $blocks->items(),
            'meta' => [
                'current_page' => $blocks->currentPage(),
                'last_page' => $blocks->lastPage(),
                'per_page' => $blocks->perPage(),
                'total' => $blocks->total(),
            ],
        ]);
    }

    /**
     * Create a maintenance block.
     *
     * @authenticated
     * @bodyParam lab_space_id integer required The lab space ID. Example: 1
     * @bodyParam title string required Block title. Example: Annual Maintenance
     * @bodyParam reason string Reason for maintenance. Example: Equipment calibration
     * @bodyParam starts_at datetime required Start time. Example: 2024-01-15 08:00:00
     * @bodyParam ends_at datetime required End time. Example: 2024-01-15 18:00:00
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Maintenance block created",
     *   "data": {...}
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('manage_lab_spaces');

        $validated = $request->validate([
            'lab_space_id' => ['required', 'exists:lab_spaces,id'],
            'title' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'starts_at' => ['required', 'date', 'after_or_equal:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);

        $block = LabMaintenanceBlock::create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Maintenance block created',
            'data' => $block->load(['labSpace:id,name,slug', 'creator:id,username']),
        ], 201);
    }

    /**
     * Get a maintenance block.
     *
     * @urlParam id integer required The block ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {...}
     * }
     */
    public function show(int $id): JsonResponse
    {
        $this->authorize('manage_lab_spaces');

        $block = LabMaintenanceBlock::with(['labSpace:id,name,slug', 'creator:id,username'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $block,
        ]);
    }

    /**
     * Update a maintenance block.
     *
     * @urlParam id integer required The block ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Maintenance block updated",
     *   "data": {...}
     * }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('manage_lab_spaces');

        $block = LabMaintenanceBlock::findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date', 'after:starts_at'],
        ]);

        $block->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Maintenance block updated',
            'data' => $block->load(['labSpace:id,name,slug', 'creator:id,username']),
        ]);
    }

    /**
     * Delete a maintenance block.
     *
     * @urlParam id integer required The block ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Maintenance block deleted"
     * }
     */
    public function destroy(int $id): JsonResponse
    {
        $this->authorize('manage_lab_spaces');

        $block = LabMaintenanceBlock::findOrFail($id);
        $block->delete();

        return response()->json([
            'success' => true,
            'message' => 'Maintenance block deleted',
        ]);
    }
}
