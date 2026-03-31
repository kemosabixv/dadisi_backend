<?php

namespace App\Notifications;

use App\Channels\SupabaseChannel;
use App\Models\LabBooking;
use App\Models\Refund;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to users when they submit a refund request for a lab booking.
 */
class LabBookingRefundSubmitted extends Notification
{
    public function __construct(
        protected LabBooking $booking,
        protected Refund $refund
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', SupabaseChannel::class, \NotificationChannels\OneSignal\OneSignalChannel::class];
    }

    /**
     * Get the OneSignal representation of the notification.
     *
     * @param mixed $notifiable
     * @return \NotificationChannels\OneSignal\OneSignalMessage
     */
    public function toOneSignal($notifiable)
    {
        return \NotificationChannels\OneSignal\OneSignalMessage::create()
            ->setSubject('Refund Request Received')
            ->setBody("Your refund request for lab booking #" . ($this->booking->booking_reference ?: $this->booking->id) . " has been submitted.")
            ->setUrl(config('app.frontend_url') . "/dashboard/bookings")
            ->setData('type', 'lab_booking_refund_submitted')
            ->setData('booking_id', $this->booking->id);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amountStr = "{$this->refund->currency} " . number_format($this->refund->amount, 2);
        
        return (new MailMessage)
            ->subject('Refund Request Received')
            ->greeting('Hello ' . $this->booking->payer_name . ',')
            ->line("We've received your refund request for your lab space booking.")
            ->line("**Booking Reference:** " . ($this->booking->booking_reference ?: $this->booking->id))
            ->line("**Refund Amount:** {$amountStr}")
            ->line("Your request is currently being reviewed by our staff. You will receive another notification once a decision is made.")
            ->line("Review usually takes 1-2 business days.")
            ->action('Track Status', config('app.frontend_url') . '/dashboard/bookings');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lab_booking_refund_submitted',
            'title' => 'Refund Request Pending',
            'message' => "Your refund request of {$this->refund->currency} " . number_format($this->refund->amount, 2) . " for booking " . ($this->booking->booking_reference ?: $this->booking->id) . " has been submitted for review.",
            'booking_id' => $this->booking->id,
            'refund_id' => $this->refund->id,
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
