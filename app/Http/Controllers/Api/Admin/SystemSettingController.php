<?php

namespace App\Http\Controllers\Api\Admin;

use App\DTOs\UpdateSystemSettingDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSystemSettingRequest;
use App\Http\Resources\SystemSettingResource;
use App\Services\Contracts\SystemSettingServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group Admin - System Settings
 * @groupDescription Administrative endpoints for managing global system configurations and environment variables.
 */
class SystemSettingController extends Controller
{
    public function __construct(
        private SystemSettingServiceContract $settingService
    ) {
        $this->middleware(['auth', 'admin']);
    }

    /**
     * List System Settings
     *
     * Retrieve a key-value list of system settings. Can be filtered by group (e.g., 'pesapal', 'email').
     *
     * @authenticated
     * @queryParam group string Filter settings by group name. Example: pesapal
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "pesapal.environment": "sandbox",
     *     "pesapal.consumer_key": "key_123",
     *     "pesapal.enabled": true
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $settings = $this->settingService->getSettings($request->query('group'));

            return response()->json([
                'success' => true,
                'data' => $settings,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to list system settings', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve settings'], 500);
        }
    }

    /**
     * Bulk Update System Settings
     *
     * Update multiple system settings at once. Keys that don't exist will be created.
     * The system automatically infers the group (prefix before dot) and type (boolean/string/float) for new keys.
     *
     * @authenticated
     * @bodyParam pesapal.consumer_key string Example: new_key_123
     * @bodyParam pesapal.enabled boolean Example: true
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Settings updated successfully",
     *   "data": {
     *     "pesapal.consumer_key": "new_key_123",
     *     "pesapal.enabled": true
     *   }
     * }
     */
    public function update(UpdateSystemSettingRequest $request): JsonResponse
    {
        try {
            $dto = UpdateSystemSettingDTO::fromArray($request->validated());
            $updatedSettings = $this->settingService->updateSettings($dto->toArray(), $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => $updatedSettings,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update system settings', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to update settings'], 500);
        }
    }
}
