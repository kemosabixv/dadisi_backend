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

    /**
     * Dispatch donation initiated notification (for guests)
     */
    public function sendDonationInitiated(\App\Models\Donation $donation): void;

    /**
     * Dispatch donation received notification
     */
    public function sendDonationReceived(\App\Models\Donation $donation): void;

    /**
     * Dispatch donation cancelled notification
     */
    public function sendDonationCancelled(\App\Models\Donation $donation): void;

    /**
     * Dispatch donation refunded notification
     */
    public function sendDonationRefunded(\App\Models\Donation $donation): void;

    /**
     * Send donation reminder notification
     */
    public function sendDonationReminder(\App\Models\Donation $donation): void;

    /**
     * Send donation payment failed notification
     */
    public function sendDonationPaymentFailed(\App\Models\Donation $donation): void;

    /**
     * Dispatch subscription activated notification
     */
    public function sendSubscriptionActivated(\App\Models\PlanSubscription $subscription): void;

    /**
     * Dispatch subscription cancelled notification
     */
    public function sendSubscriptionCancelled(\App\Models\PlanSubscription $subscription, ?string $reason = null): void;

    /**
     * Dispatch subscription payment failed notification
     */
    public function sendSubscriptionPaymentFailed(\App\Models\PlanSubscription $subscription, string $error): void;

    /**
     * Dispatch subscription renewal reminder
     */
    public function sendSubscriptionReminder(\App\Models\PlanSubscription $subscription, int $daysRemaining): void;

    /**
     * Dispatch lab booking initiated notification (payment session started)
     */
    public function sendLabBookingInitiated(\App\Models\LabBooking $booking, string $paymentUrl): void;

    /**
     * Dispatch lab booking confirmation notification
     */
    public function sendLabBookingConfirmation(\App\Models\LabBooking $booking): void;

    /**
     * Dispatch lab booking cancelled notification
     */
    public function sendLabBookingCancelled(\App\Models\LabBooking $booking): void;

    /**
     * Dispatch lab booking reminder notification
     */
    public function sendLabBookingReminder(\App\Models\LabBooking $booking): void;
}
