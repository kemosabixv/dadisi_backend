<?php

namespace App\Notifications;

use App\Channels\SupabaseChannel;
use App\Models\LabBooking;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

/**
 * Sent when a lab booking is cancelled.
 * Tier 1 (synchronous).
 */
class LabBookingCancelled extends Notification
{
    public function __construct(protected LabBooking $booking) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return ['mail'];
        }
        return ['mail', 'database', SupabaseChannel::class, OneSignalChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $space = $booking->labSpace;
        $startsAt = $booking->starts_at->format('l, F j, Y \a\t g:i A');

        $mail = (new MailMessage)
            ->subject("Booking Cancelled: {$space->name}")
            ->greeting('Hello ' . $booking->payer_name . ',')
            ->line("Your lab space booking has been cancelled.")
            ->line("**Lab Space:** {$space->name}")
            ->line("**Original Date:** {$startsAt}");

        if ($booking->total_price > 0) {
            $mail->line('')
                ->line('If you are eligible for a refund, it will be processed within 3-5 business days.');
        }

        if ($booking->is_guest) {
            $mail->line('')
                ->line('If you have any questions, please reply to this email or contact us at support@dadisilab.com.');
        } else {
            $frontendUrl = config('app.frontend_url', url('/'));
            $mail->action('View My Bookings', $frontendUrl . '/dashboard/bookings');
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        $booking = $this->booking;
        return [
            'type' => 'lab_booking_cancelled',
            'title' => 'Lab Booking Cancelled',
            'message' => "Your booking for {$booking->labSpace->name} on {$booking->starts_at->format('M j')} has been cancelled.",
            'booking_id' => $booking->id,
            'lab_space' => $booking->labSpace->name,
            'starts_at' => $booking->starts_at->toISOString(),
            'link' => '/dashboard/bookings',
        ];
    }

    public function toSupabase(object $notifiable): array
    {
        $data = $this->toArray($notifiable);
        $data['recipient_type'] = 'user';
        return $data;
    }

    /**
     * Get the OneSignal representation of the notification.
     *
     * @param mixed $notifiable
     * @return \NotificationChannels\OneSignal\OneSignalMessage
     */
    public function toOneSignal($notifiable)
    {
        $space = $this->booking->labSpace;
        return OneSignalMessage::create()
            ->setSubject("Booking Cancelled: {$space->name}")
            ->setBody("Your booking for {$space->name} has been cancelled.")
            ->setUrl(config('app.frontend_url') . '/dashboard/bookings')
            ->setData('type', 'lab_booking_cancelled')
            ->setData('booking_id', $this->booking->id);
    }
}
