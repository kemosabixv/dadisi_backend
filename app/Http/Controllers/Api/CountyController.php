<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\County;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CountyController extends Controller
{
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
     *   "data": [
     *     {"id": 1, "name": "Baringo"},
     *     {"id": 2, "name": "Bomet"}
     *   ]
     * }
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', County::class);
        $counties = County::select('id', 'name')->orderBy('name')->get();
        return response()->json(['data' => $counties]);
    }

    /**
     * Get a single county (PUBLIC)
     * 
     * @group Counties
     * @unauthenticated
     * @urlParam county integer required The ID of the county. Example: 1
     * 
     * @response 200 {"data": {"id": 1, "name": "Nairobi"}}
     */
    public function show(County $county): JsonResponse
    {
        $this->authorize('view', $county);
        return response()->json(['data' => $county]);
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
     * @response 201 {"data": {"id": 48, "name": "New County"}, "message": "County created"}
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', County::class);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:counties,name',
        ]);
        
        $county = County::create($validated);
        
        return response()->json([
            'data' => $county,
            'message' => 'County created successfully',
        ], 201);
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
     * @response 200 {"data": {"id": 1, "name": "Updated Name"}, "message": "County updated"}
     */
    public function update(Request $request, County $county): JsonResponse
    {
        $this->authorize('update', $county);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:counties,name,' . $county->id,
        ]);
        
        $county->update($validated);
        
        return response()->json([
            'data' => $county,
            'message' => 'County updated successfully',
        ]);
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
        $this->authorize('delete', $county);
        
        $county->delete();
        
        return response()->json([
            'message' => 'County deleted successfully',
        ]);
    }
}
