<?php

namespace App\Notifications;

use App\Channels\SupabaseChannel;
use App\Models\LabBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent 1 day before a lab session as a reminder.
 * Tier 2 (queued via ShouldQueue).
 */
class LabBookingReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected LabBooking $booking) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return ['mail'];
        }
        return ['mail', 'database', SupabaseChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $space = $booking->labSpace;
        $startsAt = $booking->starts_at->format('l, F j, Y \a\t g:i A');
        $endsAt = $booking->ends_at->format('g:i A');
        $isGuest = $booking->is_guest;

        $mail = (new MailMessage)
            ->subject("Reminder: Lab Session Tomorrow – {$space->name}")
            ->greeting('Hello ' . $booking->payer_name . ',')
            ->line("This is a friendly reminder about your upcoming lab session.")
            ->line("**Lab Space:** {$space->name}")
            ->line("**Date & Time:** {$startsAt} – {$endsAt}");

        if ($space->location) {
            $mail->line("**Location:** {$space->location}");
        }

        if ($isGuest) {
            $mail->line('')
                ->line('**Check-in:** Our lab staff will record your attendance on arrival.')
                ->line('')
                ->line('*Tip: Register an account at dadisilab.com to self-check-in and manage bookings for future visits.*');
        } else {
            $mail->line('')
                ->line('**Check-in:** Use the QR scanner in your dashboard to check in when you arrive.')
                ->action('View My Bookings', config('app.frontend_url', url('/')) . '/dashboard/bookings');
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        $booking = $this->booking;
        return [
            'type' => 'lab_booking_reminder',
            'title' => 'Lab Session Tomorrow',
            'message' => "Your {$booking->labSpace->name} session starts tomorrow at {$booking->starts_at->format('g:i A')}.",
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
}
