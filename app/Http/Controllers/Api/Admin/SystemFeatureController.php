<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group System Features
 *
 * APIs for managing system features that can be associated with subscription plans.
 * System features are built-in and cannot be deleted - only their active status can be toggled.
 */
class SystemFeatureController extends Controller
{
    /**
     * List All System Features
     *
     * Returns all system features for display in the plan dialog.
     *
     * @queryParam active boolean Filter to active features only. Example: true
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
        $query = SystemFeature::query()->sorted();

        if ($request->boolean('active', true)) {
            $query->active();
        }

        $features = $query->get();

        return response()->json([
            'success' => true,
            'data' => $features,
        ]);
    }

    /**
     * Get Single System Feature
     *
     * @urlParam feature int required The feature ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "name": "Event Creation Limit",
     *     "slug": "event_creation_limit",
     *     "description": "Maximum events per month",
     *     "value_type": "number",
     *     "default_value": "0",
     *     "is_active": true
     *   }
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
     * Update a system feature's details. Cannot delete - only update.
     *
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
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'default_value' => 'sometimes|string',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $feature->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Feature updated successfully',
            'data' => $feature->fresh(),
        ]);
    }

    /**
     * Toggle Feature Active Status
     *
     * Quickly toggle a feature's active status.
     *
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
        $feature->update(['is_active' => !$feature->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Feature status toggled',
            'data' => [
                'is_active' => $feature->is_active,
            ],
        ]);
    }
}
