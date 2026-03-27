<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;

/**
 * @group Public Settings
 * @groupDescription Public endpoints for retrieving site-wide settings (brand, contact, social links, etc.)
 */
class PublicSettingController extends Controller
{
    /**
     * Get Public Settings
     *
     * Returns all system settings marked as public, keyed by setting key.
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "brand_name": "Dadisi Community Labs",
     *     "brand_description": "Discovering together...",
     *     "contact_email": "info@dadisilab.com",
     *     "social_links": { "facebook": "...", "twitter": "..." }
     *   }
     * }
     */
    public function index(): JsonResponse
    {
        $settings = SystemSetting::where('is_public', true)
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->key => $item->value];
            });

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }
}
