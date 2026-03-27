<?php

namespace App\Notifications;

use App\Channels\SupabaseChannel;
use App\Models\LabBooking;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a lab booking payment session is initiated.
 * Contains the payment link so guests/users can resume if interrupted.
 * Tier 1 (synchronous).
 */
class LabBookingInitiated extends Notification
{
    public function __construct(
        protected LabBooking $booking,
        protected string $paymentUrl
    ) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return ['mail'];
        }
        return ['mail', 'database', SupabaseChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $space = $this->booking->labSpace;
        $startsAt = $this->booking->starts_at->format('l, F j, Y \a\t g:i A');
        $duration = $this->booking->duration_hours;
        $amount = number_format($this->booking->total_price, 2) . ' KES';

        return (new MailMessage)
            ->subject("Complete Your Lab Booking: {$space->name}")
            ->greeting('Hello ' . $this->booking->payer_name . ',')
            ->line("You've started a booking for **{$space->name}**.")
            ->line("**Date & Time:** {$startsAt}")
            ->line("**Duration:** {$duration} hours")
            ->line("**Amount Due:** {$amount}")
            ->line('Complete your payment to confirm your booking.')
            ->action('Complete Payment', $this->paymentUrl)
            ->line('This link will remain active until the session date.')
            ->line('')
            ->line('*Tip: Register an account at dadisilab.com to manage bookings, self-check-in, and track your lab usage.*');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lab_booking_initiated',
            'title' => 'Lab Booking Payment Pending',
            'message' => "Complete payment for your {$this->booking->labSpace->name} booking.",
            'booking_id' => $this->booking->id,
            'lab_space' => $this->booking->labSpace->name,
            'starts_at' => $this->booking->starts_at->toISOString(),
            'amount' => (float) $this->booking->total_price,
            'payment_url' => $this->paymentUrl,
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
