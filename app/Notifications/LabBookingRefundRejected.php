<?php

namespace App\Notifications;

use App\Channels\SupabaseChannel;
use App\Models\LabBooking;
use App\Models\Refund;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to users when their lab booking refund is rejected.
 */
class LabBookingRefundRejected extends Notification
{
    public function __construct(
        protected LabBooking $booking,
        protected Refund $refund,
        protected ?string $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', SupabaseChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Refund Request Update: Lab Booking')
            ->greeting('Hello ' . $this->booking->payer_name . ',')
            ->line("We are writing to inform you that your refund request for lab space booking " . ($this->booking->booking_reference ?: $this->booking->id) . " was not approved.")
            ->line("**Reason for Rejection:** " . ($this->reason ?: 'Does not meet cancellation policy requirements.'));

        $mail->line("If you have any questions regarding this decision, please contact our support team.")
             ->action('Contact Support', 'mailto:support@dadisilab.com');

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lab_booking_refund_rejected',
            'title' => 'Refund Not Approved',
            'message' => "Your refund request for booking " . ($this->booking->booking_reference ?: $this->booking->id) . " was not approved.",
            'booking_id' => $this->booking->id,
            'refund_id' => $this->refund->id,
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
