<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @group System Settings
 *
 * APIs for managing global system configuration.
 */
class SystemSettingController extends Controller
{
    /**
     * List System Settings
     *
     * Retrieve a key-value list of system settings. Can be filtered by group (e.g., 'pesapal', 'email').
     *
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
    public function index(Request $request)
    {
        $query = SystemSetting::query();

        if ($request->has('group')) {
            $query->where('group', $request->group);
        }

        $settings = $query->get()->mapWithKeys(function ($item) {
            return [$item->key => $item->value]; // Accessor handles casting
        });

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Bulk Update System Settings
     *
     * Update multiple system settings at once. Keys that don't exist will be created.
     * The system automatically infers the group (prefix before dot) and type (boolean/string/float) for new keys.
     *
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
    public function update(Request $request)
    {
        $data = $request->all();
        $updatedSettings = [];

        foreach ($data as $key => $value) {
            // Determine group and type based on key naming convention or existing record
            $setting = SystemSetting::firstOrNew(['key' => $key]);

            // Infer properties if new
            if (!$setting->exists) {
                $setting->group = explode('.', $key)[0] ?? 'general';
                $setting->type = is_bool($value) ? 'boolean' : (is_numeric($value) ? 'float' : 'string');
            }

            $setting->value = $value;
            $setting->updated_by = $request->user()->id;
            $setting->save();

            $updatedSettings[$key] = $setting->value;
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => $updatedSettings,
        ]);
    }
}
