<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Handle Web Push Subscriptions
 * 
 * Manages the registration and removal of PWA push notification subscriptions
 */
class PushNotificationController extends Controller
{
    /**
     * Store or update a push subscription
     * 
     * @group Notifications
     * @authenticated
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'endpoint' => 'required',
            'keys.auth' => 'required',
            'keys.p256dh' => 'required',
        ]);

        try {
            $endpoint = $request->endpoint;
            $key = $request->keys['p256dh'];
            $token = $request->keys['auth'];

            $request->user()->updatePushSubscription($endpoint, $key, $token);

            return response()->json([
                'success' => true,
                'message' => 'Push subscription saved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save push subscription', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save push subscription'
            ], 500);
        }
    }

    /**
     * Delete a push subscription
     * 
     * @group Notifications
     * @authenticated
     */
    public function destroy(Request $request): JsonResponse
    {
        $this->validate($request, ['endpoint' => 'required']);

        try {
            $request->user()->deletePushSubscription($request->endpoint);

            return response()->json([
                'success' => true,
                'message' => 'Push subscription removed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to remove push subscription', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove push subscription'
            ], 500);
        }
    }
}
