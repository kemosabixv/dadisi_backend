<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemFeature;
use App\Services\Contracts\SystemFeatureServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group Admin - System Features
 * @groupDescription Administrative endpoints for managing system features associated with subscription plans.
 */
class SystemFeatureController extends Controller
{
    public function __construct(
        private SystemFeatureServiceContract $featureService
    ) {
        $this->middleware(['auth:sanctum', 'admin']);
    }

    /**
     * List All System Features
     *
     * Returns all system features for display in the plan dialog.
     *
     * @authenticated
     * @queryParam active boolean Filter to active features only (default: true). Example: true
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Event Creation Limit",
     *       "slug": "event_creation_limit",
     *       "description": "Maximum events per month",
     *       "value_type": "number",
     *       "default_value": "0",
     *       "is_active": true,
     *       "sort_order": 1
     *     }
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $features = $this->featureService->listFeatures($request->boolean('active', true));

            return response()->json([
                'success' => true,
                'data' => $features,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to list system features', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve features'], 500);
        }
    }

    /**
     * Get Single System Feature
     *
     * @authenticated
     * @urlParam feature int required The feature ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": { ... }
     * }
     */
    public function show(SystemFeature $feature): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $feature,
        ]);
    }

    /**
     * Update System Feature
     *
     * Update a system feature's details. System features cannot be deleted, only deactivated.
     *
     * @authenticated
     * @urlParam feature int required The feature ID. Example: 1
     * @bodyParam name string The display name. Example: Event Creation Limit
     * @bodyParam description string The description. Example: Maximum events per month
     * @bodyParam default_value string The default value. Example: 0
     * @bodyParam is_active boolean Whether the feature is active. Example: true
     * @bodyParam sort_order integer Display order. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Feature updated successfully",
     *   "data": { ... }
     * }
     */
    public function update(Request $request, SystemFeature $feature): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'sometimes|nullable|string',
                'default_value' => 'sometimes|string',
                'is_active' => 'sometimes|boolean',
                'sort_order' => 'sometimes|integer|min:0',
            ]);

            $updatedFeature = $this->featureService->updateFeature($feature, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Feature updated successfully',
                'data' => $updatedFeature,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update system feature', ['error' => $e->getMessage(), 'feature_id' => $feature->id]);
            return response()->json(['success' => false, 'message' => 'Failed to update feature'], 500);
        }
    }

    /**
     * Toggle Feature Active Status
     *
     * Quickly toggle a feature's active status.
     *
     * @authenticated
     * @urlParam feature int required The feature ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Feature status toggled",
     *   "data": { "is_active": false }
     * }
     */
    public function toggle(SystemFeature $feature): JsonResponse
    {
        try {
            $updatedFeature = $this->featureService->toggleFeatureStatus($feature);

            return response()->json([
                'success' => true,
                'message' => 'Feature status toggled',
                'data' => [
                    'is_active' => $updatedFeature->is_active,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to toggle feature status', ['error' => $e->getMessage(), 'feature_id' => $feature->id]);
            return response()->json(['success' => false, 'message' => 'Failed to toggle status'], 500);
        }
    }
}
