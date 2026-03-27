<?php

namespace App\Services;

use App\Services\Contracts\NotificationServiceContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Models\Donation;
use App\Models\User;
use App\Models\LabBooking;
use App\Notifications\DonationInitiated;
use App\Notifications\DonationReceived;
use App\Notifications\DonationPaymentFailed;
use App\Notifications\DonationCancelled;
use App\Notifications\DonationRefunded;
use App\Notifications\DonationReminder;
use App\Notifications\SubscriptionActivated;

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

    /**
     * Dispatch donation initiated notification (for guests)
     */
    public function sendDonationInitiated(Donation $donation): void
    {
        $this->notifyDonor($donation, new DonationInitiated($donation));
    }

    /**
     * Dispatch donation received notification
     */
    public function sendDonationReceived(Donation $donation): void
    {
        $this->notifyDonor($donation, new DonationReceived($donation));
    }

    /**
     * Dispatch donation cancelled notification
     */
    public function sendDonationCancelled(Donation $donation): void
    {
        $this->notifyDonor($donation, new DonationCancelled($donation));
    }

    /**
     * Dispatch donation refunded notification
     */
    public function sendDonationRefunded(Donation $donation): void
    {
        $this->notifyDonor($donation, new DonationRefunded($donation));
    }

    /**
     * Send donation reminder notification
     */
    public function sendDonationReminder(Donation $donation): void
    {
        $this->notifyDonor($donation, new DonationReminder($donation));
    }

    /**
     * Send donation payment failed notification
     */
    public function sendDonationPaymentFailed(Donation $donation): void
    {
        $this->notifyDonor($donation, new DonationPaymentFailed($donation));
    }

    /**
     * Dispatch subscription activated notification
     */
    public function sendSubscriptionActivated(\App\Models\PlanSubscription $subscription): void
    {
        try {
            $user = User::find($subscription->subscriber_id);
            if ($user) {
                $user->notify(new SubscriptionActivated($subscription));
            }
        } catch (\Exception $e) {
            Log::error('Failed to dispatch subscription activated notification', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Dispatch subscription cancelled notification
     */
    public function sendSubscriptionCancelled(\App\Models\PlanSubscription $subscription, ?string $reason = null): void
    {
        try {
            $user = User::find($subscription->subscriber_id);
            if ($user) {
                $user->notify(new \App\Notifications\SubscriptionCancelled($subscription, $reason));
            }
        } catch (\Exception $e) {
            Log::error('Failed to dispatch subscription cancelled notification', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Dispatch subscription payment failed notification
     */
    public function sendSubscriptionPaymentFailed(\App\Models\PlanSubscription $subscription, string $error): void
    {
        try {
            $user = User::find($subscription->subscriber_id);
            if ($user) {
                $user->notify(new \App\Notifications\SubscriptionPaymentFailed($subscription, $error));
            }
        } catch (\Exception $e) {
            Log::error('Failed to dispatch subscription payment failed notification', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Dispatch subscription renewal reminder
     */
    public function sendSubscriptionReminder(\App\Models\PlanSubscription $subscription, int $daysRemaining): void
    {
        try {
            $user = User::find($subscription->subscriber_id);
            if ($user) {
                $user->notify(new \App\Notifications\SubscriptionReminder($subscription, $daysRemaining));
            }
        } catch (\Exception $e) {
            Log::error('Failed to dispatch subscription reminder notification', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Helper to notify either user or guest donor
     */
    protected function notifyDonor(Donation $donation, $notification): void
    {
        try {
            if ($donation->user_id) {
                $user = User::find($donation->user_id);
                if ($user) {
                    $user->notify($notification);
                    return;
                }
            }

            if ($donation->donor_email) {
                Notification::route('mail', $donation->donor_email)
                    ->notify($notification);
            }
        } catch (\Exception $e) {
            Log::error('Failed to dispatch donation notification', [
                'donation_id' => $donation->id,
                'notification' => get_class($notification),
                'error' => $e->getMessage()
            ]);
        }
    }

    // ==================== Lab Booking Notifications ====================

    /**
     * Dispatch lab booking initiated notification (payment session started)
     */
    public function sendLabBookingInitiated(LabBooking $booking, string $paymentUrl): void
    {
        $this->notifyBooker($booking, new \App\Notifications\LabBookingInitiated($booking, $paymentUrl));
    }

    /**
     * Dispatch lab booking confirmation notification
     */
    public function sendLabBookingConfirmation(LabBooking $booking): void
    {
        $this->notifyBooker($booking, new \App\Notifications\LabBookingConfirmation($booking));
    }

    /**
     * Dispatch lab booking cancelled notification
     */
    public function sendLabBookingCancelled(LabBooking $booking): void
    {
        $this->notifyBooker($booking, new \App\Notifications\LabBookingCancelled($booking));
    }

    /**
     * Dispatch lab booking reminder notification
     */
    public function sendLabBookingReminder(LabBooking $booking): void
    {
        $this->notifyBooker($booking, new \App\Notifications\LabBookingReminder($booking));
    }

    /**
     * Helper to notify either user or guest booker
     */
    protected function notifyBooker(LabBooking $booking, $notification): void
    {
        try {
            if ($booking->user_id) {
                $user = User::find($booking->user_id);
                if ($user) {
                    $user->notify($notification);
                    return;
                }
            }

            if ($booking->guest_email) {
                Notification::route('mail', $booking->guest_email)
                    ->notify($notification);
            }
        } catch (\Exception $e) {
            Log::error('Failed to dispatch lab booking notification', [
                'booking_id' => $booking->id,
                'notification' => get_class($notification),
                'error' => $e->getMessage()
            ]);
        }
    }
}

