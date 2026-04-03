<?php

namespace App\Notifications;

use App\Channels\SupabaseChannel;
use App\Models\LabBooking;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

/**
 * Sent when a lab booking is confirmed (payment success or free booking).
 * Guest emails include cancel/refund instructions and check-in note.
 * Tier 1 (synchronous).
 */
class LabBookingConfirmation extends Notification
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
        $endsAt = $booking->ends_at->format('g:i A');
        $duration = $booking->duration_hours;
        $isGuest = $booking->is_guest;
        $frontendUrl = config('app.frontend_url', url('/'));

        $mail = (new MailMessage)
            ->subject("Booking Confirmed: {$space->name}")
            ->greeting('Hello ' . $booking->payer_name . ',')
            ->line("Your lab space booking has been confirmed!")
            ->line("**Lab Space:** {$space->name}")
            ->line("**Date & Time:** {$startsAt} – {$endsAt}")
            ->line("**Duration:** {$duration} hours");

        if ($booking->total_price > 0) {
            $amount = number_format($booking->total_price, 2) . ' KES';
            $mail->line("**Amount Paid:** {$amount}");
        } else {
            $mail->line("**Cost:** Covered by your subscription quota");
        }

        if ($booking->receipt_number) {
            $mail->line("**Receipt:** {$booking->receipt_number}");
        }

        // Check-in instructions differ for guests vs registered users
        if ($isGuest) {
            $mail->line('')
                ->line('**Check-in:** On the day of your session, our lab staff will record your attendance on arrival. No account registration is needed.')
                ->line('')
                ->line('**Need to cancel?** Reply to this email or contact us at support@dadisilab.com. If your session is more than 24 hours away, you are eligible for a full refund.')
                ->line('')
                ->line('*Tip: Register an account at dadisilab.com to self-check-in, manage bookings, and track your lab usage for future visits.*');
        } else {
            $mail->line('')
                ->line('**Check-in:** On the day of your session, use the QR scanner in your dashboard to check in.')
                ->action('View My Bookings', $frontendUrl . '/dashboard/bookings');
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        $booking = $this->booking;
        return [
            'type' => 'lab_booking_confirmed',
            'title' => 'Lab Booking Confirmed',
            'message' => "Your booking for {$booking->labSpace->name} on {$booking->starts_at->format('M j')} is confirmed.",
            'booking_id' => $booking->id,
            'lab_space' => $booking->labSpace->name,
            'starts_at' => $booking->starts_at->toISOString(),
            'receipt_number' => $booking->receipt_number,
            'amount' => (float) $booking->total_price,
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
        $startsAt = $this->booking->starts_at->format('M j \a\t g:i A');

        return OneSignalMessage::create()
            ->setSubject("Booking Confirmed: {$space->name}")
            ->setBody("Your lab session on {$startsAt} is confirmed.")
            ->setUrl(config('app.frontend_url') . '/dashboard/bookings')
            ->setData('type', 'lab_booking_confirmed')
            ->setData('booking_id', $this->booking->id);
    }
}
