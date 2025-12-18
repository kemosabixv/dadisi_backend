<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\UIPermissionService;

/**
 * @group Admin - Dashboard
 */
class AdminMenuController extends Controller
{
    /**
     * Get Authorized Admin Menu
     *
     * Retrieves the list of navigation menu items accessible to the currently authenticated user.
     * Delegates logic to UIPermissionService to ensure consistency with user profile capability data.
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
        $user = $request->user();
        
        // Delegate to the shared service to verify permissions and build the menu
        $service = new UIPermissionService($user);
        $menu = $service->getAuthorizedMenu();

        return response()->json([
            'success' => true,
            'data' => $menu,
        ]);
    }
}
