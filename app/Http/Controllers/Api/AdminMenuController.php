<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\MenuServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin - Dashboard
 */
class AdminMenuController extends Controller
{
    public function __construct(private MenuServiceContract $menuService) {}

    /**
     * Get Authorized Admin Menu
     *
     * Retrieves the list of navigation menu items accessible to the currently authenticated user.
     * Delegates logic to MenuService to ensure consistency with user profile capability data.
     *
     * @authenticated
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "title": "Dashboard",
     *       "path": "/admin",
     *       "icon": "dashboard"
     *     }
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $menu = $this->menuService->getAdminMenu($request->user());

            return response()->json([
                'success' => true,
                'data' => $menu,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to retrieve admin menu', ['error' => $e->getMessage(), 'user_id' => $request->user()?->id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve menu'], 500);
        }
    }
}
