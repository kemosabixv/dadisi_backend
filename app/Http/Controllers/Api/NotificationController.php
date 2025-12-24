<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

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
        $user = auth()->user();
        $perPage = min($request->get('per_page', 20), 50);

        $query = $user->notifications();

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $notifications = $query
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Transform notification data for frontend
        $notifications->getCollection()->transform(function ($notification) {
            return [
                'id' => $notification->id,
                'type' => class_basename($notification->type),
                'data' => $notification->data,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
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
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => auth()->user()->unreadNotifications()->count(),
            ],
        ]);
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
    public function markAsRead(string $id): JsonResponse
    {
        $notification = auth()->user()
            ->notifications()
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
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
    public function markAllAsRead(): JsonResponse
    {
        $count = auth()->user()->unreadNotifications()->count();
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'data' => [
                'marked_count' => $count,
            ],
        ]);
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
    public function destroy(string $id): JsonResponse
    {
        $notification = auth()->user()
            ->notifications()
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted',
        ]);
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
    public function clearAll(): JsonResponse
    {
        $count = auth()->user()->notifications()->count();
        auth()->user()->notifications()->delete();

        return response()->json([
            'success' => true,
            'message' => 'All notifications cleared',
            'data' => [
                'deleted_count' => $count,
            ],
        ]);
    }
}
