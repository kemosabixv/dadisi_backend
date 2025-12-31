<?php

namespace App\Services\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Notification Service Contract
 *
 * Defines the interface for user notification operations.
 */
interface NotificationServiceContract
{
    /**
     * Get user notifications with optional filtering
     *
     * @param Authenticatable $user The user
     * @param array $filters Filtering options (unread_only, per_page)
     * @return LengthAwarePaginator
     */
    public function getUserNotifications(Authenticatable $user, array $filters = []): LengthAwarePaginator;

    /**
     * Get unread notification count
     *
     * @param Authenticatable $user The user
     * @return int
     */
    public function getUnreadCount(Authenticatable $user): int;

    /**
     * Mark a single notification as read
     *
     * @param Authenticatable $user The user
     * @param string $notificationId
     * @return bool
     */
    public function markAsRead(Authenticatable $user, string $notificationId): bool;

    /**
     * Mark all unread notifications as read
     *
     * @param Authenticatable $user The user
     * @return int Count of marked notifications
     */
    public function markAllAsRead(Authenticatable $user): int;

    /**
     * Delete a specific notification
     *
     * @param Authenticatable $user The user
     * @param string $notificationId
     * @return bool
     */
    public function deleteNotification(Authenticatable $user, string $notificationId): bool;

    /**
     * Delete all notifications for user
     *
     * @param Authenticatable $user The user
     * @return int Count of deleted notifications
     */
    public function clearAll(Authenticatable $user): int;
}
