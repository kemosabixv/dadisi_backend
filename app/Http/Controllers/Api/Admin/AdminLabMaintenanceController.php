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
        protected \App\Services\LabBookingService $bookingService
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
        $block = LabMaintenanceBlock::with(['labSpace', 'creator'])->findOrFail($id);
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
}
