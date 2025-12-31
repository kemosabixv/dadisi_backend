<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\County;
use App\Services\Contracts\CountyServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CountyController extends Controller
{
    public function __construct(private CountyServiceContract $countyService)
    {
    }
    /**
     * List all counties (PUBLIC)
     * 
     * Retrieves a list of all available counties, sorted alphabetically.
     * This is a public endpoint for populating dropdowns.
     * 
     * @group Counties
     * @unauthenticated
     * 
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {"id": 1, "name": "Nairobi"},
     *     {"id": 2, "name": "Mombasa"},
     *     {"id": 3, "name": "Kisumu"}
     *   ]
     * }
     */
    public function index(): JsonResponse
    {
        try {
            $this->authorize('viewAny', County::class);
            $counties = $this->countyService->listCounties();
            return response()->json(['success' => true, 'data' => $counties]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve counties', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve counties'], 500);
        }
    }

    /**
     * Get a single county (PUBLIC)
     * 
     * @group Counties
     * @unauthenticated
     * @urlParam county integer required The ID of the county. Example: 1
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {"id": 1, "name": "Nairobi"}
     * }
     */
    public function show(County $county): JsonResponse
    {
        try {
            $this->authorize('view', $county);
            $retrieved = $this->countyService->getCounty($county);
            return response()->json(['success' => true, 'data' => $retrieved]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve county', ['error' => $e->getMessage(), 'county_id' => $county->id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve county'], 500);
        }
    }

    /**
     * Create a county (ADMIN)
     * 
     * Requires manage_counties permission.
     * 
     * @group Counties
     * @authenticated
     * @bodyParam name string required The name of the county. Example: Nairobi
     * 
     * @response 201 {
     *   "success": true,
     *   "data": {"id": 48, "name": "Nakuru"},
     *   "message": "County created successfully"
     * }
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $this->authorize('create', County::class);
            
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:counties,name',
            ]);
            
            $county = $this->countyService->createCounty($validated);
            
            return response()->json([
                'success' => true,
                'data' => $county,
                'message' => 'County created successfully',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create county', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create county'], 500);
        }
    }

    /**
     * Update a county (ADMIN)
     * 
     * Requires manage_counties permission.
     * 
     * @group Counties
     * @authenticated
     * @urlParam county integer required The ID of the county. Example: 1
     * @bodyParam name string required The name of the county. Example: Nairobi
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {"id": 1, "name": "Nairobi City"},
     *   "message": "County updated successfully"
     * }
     */
    public function update(Request $request, County $county): JsonResponse
    {
        try {
            $this->authorize('update', $county);
            
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:counties,name,' . $county->id,
            ]);
            
            $updated = $this->countyService->updateCounty($county, $validated);
            
            return response()->json([
                'success' => true,
                'data' => $updated,
                'message' => 'County updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update county', ['error' => $e->getMessage(), 'county_id' => $county->id]);
            return response()->json(['success' => false, 'message' => 'Failed to update county'], 500);
        }
    }

    /**
     * Delete a county (ADMIN)
     * 
     * Requires manage_counties permission.
     * 
     * @group Counties
     * @authenticated
     * @urlParam county integer required The ID of the county. Example: 1
     * 
     * @response 200 {"message": "County deleted successfully"}
     */
    public function destroy(County $county): JsonResponse
    {
        try {
            $this->authorize('delete', $county);
            
            $this->countyService->deleteCounty($county);
            
            return response()->json([
                'success' => true,
                'message' => 'County deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete county', ['error' => $e->getMessage(), 'county_id' => $county->id]);
            return response()->json(['success' => false, 'message' => 'Failed to delete county'], 500);
        }
    }
}
