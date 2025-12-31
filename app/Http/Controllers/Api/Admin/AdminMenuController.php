<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Contracts\MenuServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Admin Menu Controller
 *
 * Dynamically generates the admin navigation menu based on user permissions.
 *
 * @group Admin - Navigation
 * @authenticated
 */
class AdminMenuController extends Controller
{
    public function __construct(
        private MenuServiceContract $menuService
    ) {
        $this->middleware(['auth:sanctum', 'admin']);
    }

    /**
     * Get Authorized Admin Menu
     *
     * Returns a dynamic list of menu items based on the authenticated user's permissions.
     * This endpoint is used by the frontend to render the admin sidebar/navigation.
     *
     * @authenticated
     *
     * @response 200 [
     *   { "key": "users", "label": "User Management", "href": "/admin/users", "icon": "Users" },
     *   { "key": "finance", "label": "Finance", "href": "/admin/finance", "icon": "DollarSign" }
     * ]
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $menu = $this->menuService->getAdminMenu($request->user());

            return response()->json($menu);
        } catch (\Exception $e) {
            Log::error('Failed to generate admin menu', ['error' => $e->getMessage(), 'user_id' => $request->user()?->id]);
            return response()->json(['success' => false, 'message' => 'Failed to generate menu'], 500);
        }
    }
}
