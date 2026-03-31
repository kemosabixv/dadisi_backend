<?php

namespace App\Notifications;

use App\Channels\SupabaseChannel;
use App\Models\LabBooking;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

class LabBookingCancelledByStaff extends Notification
{
    public function __construct(
        protected LabBooking $booking,
        protected string $reason
    ) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return ['mail'];
        }
        return ['mail', 'database', SupabaseChannel::class, OneSignalChannel::class];
    }

    /**
     * Get the OneSignal representation of the notification.
     *
     * @param mixed $notifiable
     * @return \NotificationChannels\OneSignal\OneSignalMessage
     */
    public function toOneSignal($notifiable)
    {
        return OneSignalMessage::create()
            ->setSubject('Booking Cancelled')
            ->setBody("Your booking for {$this->booking->labSpace->name} was cancelled by staff. Reason: {$this->reason}")
            ->setUrl(config('app.frontend_url') . '/dashboard/bookings')
            ->setData('type', 'lab_booking_cancelled_by_staff')
            ->setData('booking_id', $this->booking->id);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $space = $booking->labSpace;
        $startsAt = $booking->starts_at->format('l, F j, Y \a\t g:i A');

        return (new MailMessage)
            ->subject("Booking Cancelled by Staff: {$space->name}")
            ->greeting('Hello ' . ($booking->user?->name ?? $booking->guest_name ?? 'there') . ',')
            ->line("Your lab space booking has been cancelled by our staff.")
            ->line("**Reason:** {$this->reason}")
            ->line("**Lab Space:** {$space->name}")
            ->line("**Original Date:** {$startsAt}")
            ->line("If you were eligible for a refund, it has been initiated and will be processed accordingly.")
            ->action('View My Bookings', config('app.frontend_url') . '/dashboard/bookings')
            ->line('If you have any questions, please contact us at support@dadisilab.com.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lab_booking_cancelled_by_staff',
            'title' => 'Booking Cancelled by Staff',
            'message' => "Your booking for {$this->booking->labSpace->name} was cancelled by staff. Reason: {$this->reason}",
            'booking_id' => $this->booking->id,
            'reason' => $this->reason,
            'link' => '/dashboard/bookings',
        ];
    }

    public function toSupabase(object $notifiable): array
    {
        $data = $this->toArray($notifiable);
        $data['recipient_type'] = 'user';
        return $data;
    }
}
