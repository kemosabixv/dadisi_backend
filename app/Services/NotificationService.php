<?php

namespace App\Services;

use App\Services\Contracts\NotificationServiceContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

/**
 * Notification Service
 *
 * Handles user notification operations including retrieval, marking as read, and deletion.
 */
class NotificationService implements NotificationServiceContract
{
    /**
     * Get user notifications with optional filtering
     */
    public function getUserNotifications(\Illuminate\Contracts\Auth\Authenticatable $user, array $filters = []): LengthAwarePaginator
    {
        try {
            /** @var \App\Models\User $user */
            $perPage = min($filters['per_page'] ?? 20, 50);

            $query = $user->notifications();

            if ($filters['unread_only'] ?? false) {
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

            return $notifications;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve notifications', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount(\Illuminate\Contracts\Auth\Authenticatable $user): int
    {
        try {
            /** @var \App\Models\User $user */
            return $user->unreadNotifications()->count();
        } catch (\Exception $e) {
            Log::error('Failed to get unread count', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Mark a single notification as read
     */
    public function markAsRead(\Illuminate\Contracts\Auth\Authenticatable $user, string $notificationId): bool
    {
        try {
            /** @var \App\Models\User $user */
            $notification = $user
                ->notifications()
                ->where('id', $notificationId)
                ->first();

            if (!$notification) {
                throw new \Exception('Notification not found');
            }

            $notification->markAsRead();
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read', ['error' => $e->getMessage(), 'notification_id' => $notificationId]);
            throw $e;
        }
    }

    /**
     * Mark all unread notifications as read
     */
    public function markAllAsRead(\Illuminate\Contracts\Auth\Authenticatable $user): int
    {
        try {
            /** @var \App\Models\User $user */
            $count = $user->unreadNotifications()->count();
            $user->unreadNotifications->markAsRead();
            return $count;
        } catch (\Exception $e) {
            Log::error('Failed to mark all notifications as read', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Delete a specific notification
     */
    public function deleteNotification(\Illuminate\Contracts\Auth\Authenticatable $user, string $notificationId): bool
    {
        try {
            /** @var \App\Models\User $user */
            $notification = $user
                ->notifications()
                ->where('id', $notificationId)
                ->first();

            if (!$notification) {
                throw new \Exception('Notification not found');
            }

            return (bool) $notification->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete notification', ['error' => $e->getMessage(), 'notification_id' => $notificationId]);
            throw $e;
        }
    }

    /**
     * Delete all notifications for user
     */
    public function clearAll(\Illuminate\Contracts\Auth\Authenticatable $user): int
    {
        try {
            /** @var \App\Models\User $user */
            $count = $user->notifications()->count();
            $user->notifications()->delete();
            return $count;
        } catch (\Exception $e) {
            Log::error('Failed to clear all notifications', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
