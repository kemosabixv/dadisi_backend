<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\NotificationServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Notification Controller
 *
 * Handles user notification management including listing, marking as read, and deletion.
 *
 * @group Notifications
 * @groupDescription User notification endpoints for in-app notifications.
 */
class NotificationController extends Controller
{
    public function __construct(private NotificationServiceContract $notificationService)
    {
    }
    /**
     * List user notifications
     *
     * Retrieves a paginated list of the authenticated user's notifications.
     * Supports filtering by read status.
     *
     * @authenticated
     * @queryParam unread_only boolean Only return unread notifications. Example: true
     * @queryParam per_page integer Items per page (max 50). Default: 20. Example: 20
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "data": [
     *       {
     *         "id": "uuid-string",
     *         "type": "App\\Notifications\\TicketPurchaseConfirmation",
     *         "data": {
     *           "type": "ticket_purchase",
     *           "title": "Ticket Purchase Confirmed",
     *           "message": "Your ticket for Event Name has been confirmed."
     *         },
     *         "read_at": null,
     *         "created_at": "2025-12-23T12:00:00Z"
     *       }
     *     ],
     *     "unread_count": 5
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'unread_only' => $request->boolean('unread_only'),
                'per_page' => $request->input('per_page', 20),
            ];

            $notifications = $this->notificationService->getUserNotifications($request->user(), $filters);
            $unreadCount = $this->notificationService->getUnreadCount($request->user());

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'unread_count' => $unreadCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve notifications', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve notifications'], 500);
        }
    }

    /**
     * Get unread notification count
     *
     * Returns the count of unread notifications for the authenticated user.
     * Useful for badge displays.
     *
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "unread_count": 5
     *   }
     * }
     */
    public function unreadCount(Request $request): JsonResponse
    {
        try {
            $count = $this->notificationService->getUnreadCount($request->user());
            return response()->json([
                'success' => true,
                'data' => [
                    'unread_count' => $count,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get unread count', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to get unread count'], 500);
        }
    }

    /**
     * Mark notification as read
     *
     * Marks a specific notification as read.
     *
     * @authenticated
     * @urlParam id string required The notification UUID. Example: uuid-string
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Notification marked as read"
     * }
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        try {
            $this->notificationService->markAsRead($request->user(), $id);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read', ['error' => $e->getMessage(), 'notification_id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to mark notification as read'], 404);
        }
    }

    /**
     * Mark all notifications as read
     *
     * Marks all unread notifications as read for the authenticated user.
     *
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "message": "All notifications marked as read",
     *   "data": {
     *     "marked_count": 5
     *   }
     * }
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $count = $this->notificationService->markAllAsRead($request->user());

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read',
                'data' => [
                    'marked_count' => $count,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark all notifications as read', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to mark all notifications as read'], 500);
        }
    }

    /**
     * Delete a notification
     *
     * Permanently deletes a notification.
     *
     * @authenticated
     * @urlParam id string required The notification UUID. Example: uuid-string
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Notification deleted"
     * }
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->notificationService->deleteNotification($request->user(), $id);

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete notification', ['error' => $e->getMessage(), 'notification_id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to delete notification'], 404);
        }
    }

    /**
     * Clear all notifications
     *
     * Deletes all notifications for the authenticated user.
     *
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "message": "All notifications cleared",
     *   "data": {
     *     "deleted_count": 10
     *   }
     * }
     */
    public function clearAll(Request $request): JsonResponse
    {
        try {
            $count = $this->notificationService->clearAll($request->user());

            return response()->json([
                'success' => true,
                'message' => 'All notifications cleared',
                'data' => [
                    'deleted_count' => $count,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear all notifications', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to clear all notifications'], 500);
        }
    }
}
