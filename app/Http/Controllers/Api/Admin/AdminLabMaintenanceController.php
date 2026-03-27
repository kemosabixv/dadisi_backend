<?php

namespace App\Http\Controllers\Api\Admin;

use App\DTOs\CreateMaintenanceBlockDTO;
use App\DTOs\UpdateMaintenanceBlockDTO;
use App\Http\Controllers\Controller;
use App\Http\Resources\LabMaintenanceBlockResource;
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
    public function __construct(
        protected \App\Services\LabBookingService $bookingService,
        protected \App\Services\LabMaintenanceBlockService $maintenanceService
    ) {}
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
        $this->authorize('manage_lab_maintenance');

        $query = LabMaintenanceBlock::with(['labSpace:id,name,slug', 'creator:id,username']);

        // If lab_supervisor, only show assigned labs
        if (auth()->user()->hasRole('lab_supervisor')) {
            $assignedIds = auth()->user()->assignedLabSpaces()->pluck('id');
            $query->whereIn('lab_space_id', $assignedIds);
        }

        if ($request->boolean('grouped', true)) {
            $query->whereNull('recurrence_parent_id');
        }

        if ($request->has('lab_space_id')) {
            $query->where('lab_space_id', $request->lab_space_id);
        }

        if ($request->boolean('upcoming')) {
            $query->upcoming();
        }

        $perPage = $request->input('per_page', 15);
        $blocks = $query->withCount('instances')
                        ->orderBy('starts_at', 'desc')
                        ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => LabMaintenanceBlockResource::collection($blocks),
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
    public function store(\App\Http\Requests\Api\CreateLabMaintenanceBlockRequest $request): JsonResponse
    {
        $this->authorize('create', LabMaintenanceBlock::class);
        
        $dto = CreateMaintenanceBlockDTO::fromArray($request->validated());
        $block = LabMaintenanceBlock::create(array_merge(
            $dto->toArray(),
            ['created_by' => auth()->id()]
        ));

        // Roll over any conflicting bookings
        $rolloverResults = $this->bookingService->rollOverBookings($block);

        return response()->json([
            'success' => true,
            'message' => 'Maintenance block created',
            'data' => new LabMaintenanceBlockResource($block->load(['labSpace', 'creator'])),
            'rollover' => $rolloverResults,
        ], 201);
    }

    /**
     * Create a maintenance series from a recurrence rule.
     *
     * @authenticated
     * @bodyParam lab_space_id integer required The lab space ID. Example: 1
     * @bodyParam block_type string required Type: 'maintenance', 'holiday', 'closure'. Example: maintenance
     * @bodyParam reason string Reason/description. Example: Annual Equipment Calibration
     * @bodyParam rule object required The recurrence rule JSON.
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Maintenance series created",
     *   "data": {
     *      "series_id": 123,
     *      "instances_created": 12,
     *      "conflicts_identified": 5,
     *      "results": { "auto_moved": 3, "pending_resolution": 2 }
     *   }
     * }
     */
    public function storeSeries(Request $request): JsonResponse
    {
        $this->authorize('create', LabMaintenanceBlock::class);

        $validated = $request->validate([
            'lab_space_id' => 'required|exists:lab_spaces,id',
            'block_type' => 'required|string|in:maintenance,holiday,closure',
            'reason' => 'nullable|string',
            'rule' => 'required|array',
            'rule.freq' => 'required|string|in:weekly,monthly,yearly',
            'rule.seeds' => 'required|array|min:1',
            'rule.until' => 'nullable|date',
        ]);

        $space = LabSpace::findOrFail($validated['lab_space_id']);

        try {
            $results = $this->maintenanceService->createMaintenanceSeries(
                $space,
                $validated['block_type'],
                $validated['rule'],
                $validated['reason'],
                auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Maintenance series created successfully',
                'data' => $results,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
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
        $block = LabMaintenanceBlock::with(['labSpace', 'creator', 'parent'])->findOrFail($id);
        $this->authorize('view', $block);

        return response()->json([
            'success' => true,
            'data' => new LabMaintenanceBlockResource($block),
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
    public function update(\App\Http\Requests\Api\UpdateLabMaintenanceBlockRequest $request, int $id): JsonResponse
    {
        $block = LabMaintenanceBlock::findOrFail($id);
        $this->authorize('update', $block);

        $dto = UpdateMaintenanceBlockDTO::fromArray($request->validated());
        $data = array_filter($dto->toArray(), fn($v) => $v !== null);
        $block->update($data);

        // Roll over any conflicting bookings (re-evaluate if times changed)
        $rolloverResults = $this->bookingService->rollOverBookings($block->fresh());

        return response()->json([
            'success' => true,
            'message' => 'Maintenance block updated',
            'data' => new LabMaintenanceBlockResource($block->load(['labSpace', 'creator'])),
            'rollover' => $rolloverResults,
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
        $block = LabMaintenanceBlock::findOrFail($id);
        $this->authorize('delete', $block);
        $block->delete();

        return response()->json([
            'success' => true,
            'message' => 'Maintenance block deleted',
        ]);
    }

    /**
     * List all rollover events.
     *
     * @authenticated
     * @queryParam status string Filter by rollover status. Example: escalated
     * @queryParam lab_space_id integer Filter by lab space. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": [...]
     * }
     */
    public function rolloverReports(Request $request): JsonResponse
    {
        $this->authorize('view_lab_rollovers');

        $query = \App\Models\MaintenanceBlockRollover::with([
            'maintenanceBlock.labSpace',
            'originalBooking.user',
            'rolledOverBooking'
        ]);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('lab_space_id')) {
            $labId = $request->lab_space_id;
            $query->whereHas('maintenanceBlock', function($q) use ($labId) {
                $q->where('lab_space_id', $labId);
            });
        }

        $reports = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    /**
     * Retry an auto-rollover for an escalated booking.
     *
     * @authenticated
     * @urlParam id integer required The rollover record ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Rollover retried successfully"
     * }
     */
    public function retryRollover(int $id): JsonResponse
    {
        $this->authorize('retry_lab_rollovers');

        $rollover = \App\Models\MaintenanceBlockRollover::findOrFail($id);
        
        if ($rollover->status !== 'escalated' && $rollover->status !== 'pending_user') {
            return response()->json([
                'success' => false,
                'message' => 'Only escalated or pending user rollovers can be retried.'
            ], 400);
        }

        $result = $this->bookingService->retryAutoRollover($rollover);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['rollover'] ?? null
        ]);
    }


    /**
     * Update an entire maintenance series.
     *
     * @authenticated
     * @urlParam id integer required The master block ID.
     * @bodyParam block_type string required Type: 'maintenance', 'holiday', 'closure'.
     * @bodyParam reason string Reason/description.
     * @bodyParam rule object required The recurrence rule JSON.
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Maintenance series updated",
     *   "data": {...}
     * }
     */
    public function updateSeries(Request $request, int $id): JsonResponse
    {
        $this->authorize('update', LabMaintenanceBlock::findOrFail($id));

        $validated = $request->validate([
            'block_type' => 'required|string|in:maintenance,holiday,closure',
            'reason' => 'nullable|string',
            'rule' => 'required|array',
            'rule.freq' => 'required|string|in:weekly,monthly,yearly',
            'rule.seeds' => 'required|array|min:1',
            'rule.until' => 'nullable|date',
        ]);

        try {
            $results = $this->maintenanceService->updateMaintenanceSeries(
                $id,
                $validated['block_type'],
                $validated['rule'],
                $validated['reason'],
                auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Maintenance series updated successfully',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete an entire maintenance series.
     * 
     * DELETE /api/admin/lab-maintenance/series/{seriesId}
     */
    public function destroySeries(int $seriesId): JsonResponse
    {
        // Find at least one block to check authorization
        $block = LabMaintenanceBlock::where('recurrence_parent_id', $seriesId)
            ->orWhere('id', $seriesId)
            ->firstOrFail();

        $this->authorize('delete', $block);

        try {
            $this->maintenanceService->deleteMaintenanceSeries($seriesId);

            return response()->json([
                'success' => true,
                'message' => 'Maintenance series deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Mark maintenance as completed with a report.
     *
     * POST /api/admin/lab-maintenance/{id}/complete
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $block = LabMaintenanceBlock::findOrFail($id);
        $this->authorize('update', $block);

        $validated = $request->validate([
            'completion_report' => 'required|string|max:2000',
        ]);

        $block = $this->maintenanceService->completeMaintenance($id, $validated['completion_report'], auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'Maintenance marked as completed',
            'data' => new LabMaintenanceBlockResource($block),
        ]);
    }

    /**
     * Cancel a maintenance block or series.
     *
     * POST /api/admin/lab-maintenance/{id}/cancel
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $block = LabMaintenanceBlock::findOrFail($id);
        $this->authorize('delete', $block);

        $allSeries = $request->boolean('all_series', false);
        $result = $this->maintenanceService->cancelMaintenance($id, $allSeries);

        return response()->json([
            'success' => true,
            'message' => "Successfully cancelled {$result['cancelled_count']} maintenance block(s)",
        ]);
    }
}
