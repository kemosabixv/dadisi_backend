<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LabSpace;
use App\Services\LabMaintenanceBlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @group Lab Maintenance
 *
 * Endpoints for managing lab maintenance blocks, holidays, and closures.
 * @authenticated
 */
class LabMaintenanceBlockController extends Controller
{
    public function __construct(private LabMaintenanceBlockService $blockService) {}

    /**
     * Bulk create maintenance blocks, holidays, or closures
     *
     * Creates multiple blocks across selected dates and time slots for a lab space.
     * Lab supervisors can only manage blocks for their assigned labs.
     *
     * @bodyParam lab_space_id integer required The lab space ID
     * @bodyParam block_type string required Type of block: 'maintenance', 'holiday', or 'closure'
     * @bodyParam dates array required Array of dates in YYYY-MM-DD format
     * @bodyParam slots array Specific hour slots (0-23) or null for full day
     * @bodyParam reason string Optional reason/description for the block
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Blocks created successfully",
     *   "data": {
     *     "created": 5,
     *     "conflicts": 2,
     *     "dates": [...]
     *   }
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "Unauthorized to manage this lab"
     * }
     * @response 422 {
     *   "success": false,
     *   "message": "Validation failed",
     *   "errors": {}
     * }
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lab_space_id' => 'required|exists:lab_spaces,id',
            'block_type' => 'required|in:maintenance,holiday,closure',
            'dates' => 'required|array|min:1',
            'dates.*' => 'date_format:Y-m-d',
            'slots' => 'nullable|array',
            'slots.*' => 'integer|min:0|max:23',
            'reason' => 'nullable|string|max:500',
        ]);

        // Check authorization - lab supervisor can only manage their own labs
        $space = LabSpace::findOrFail($validated['lab_space_id']);

        if (!$this->canManageLab($space)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to manage this lab',
            ], 403);
        }

        try {
            $result = $this->blockService->bulkCreateBlocks(
                $space,
                $validated['block_type'],
                $validated['dates'],
                $validated['slots'] ?? null,
                $validated['reason'] ?? null,
                Auth::id()
            );

            $message = $result['conflicts'] > 0
                ? "Created {$result['created']} blocks with {$result['conflicts']} conflicting bookings"
                : "Created {$result['created']} blocks successfully";

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result,
            ], 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to create maintenance blocks', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create maintenance blocks',
            ], 500);
        }
    }

    /**
     * Check if current user can manage a lab
     */
    private function canManageLab(LabSpace $space): bool
    {
        $user = Auth::user();

        // Super admin can manage all labs
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Lab supervisors can manage their assigned labs
        if ($user->hasRole('lab_supervisor')) {
            return $space->supervisors()->where('user_id', $user->id)->exists();
        }

        return false;
    }
}
