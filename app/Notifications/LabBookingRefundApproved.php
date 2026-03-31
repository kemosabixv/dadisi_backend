<?php

namespace App\Notifications;

use App\Channels\SupabaseChannel;
use App\Models\LabBooking;
use App\Models\Refund;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to users when their lab booking refund is approved.
 */
class LabBookingRefundApproved extends Notification
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
            ->setSubject('Refund Approved')
            ->setBody("Your refund for lab booking #" . ($this->booking->booking_reference ?: $this->booking->id) . " has been approved.")
            ->setUrl(config('app.frontend_url') . "/dashboard/bookings")
            ->setData('type', 'lab_booking_refund_approved')
            ->setData('booking_id', $this->booking->id);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amountStr = "{$this->refund->currency} " . number_format($this->refund->amount, 2);
        
        return (new MailMessage)
            ->subject('Refund Approved: Lab Booking')
            ->greeting('Hello ' . $this->booking->payer_name . ',')
            ->line("Your refund request for lab space booking " . ($this->booking->booking_reference ?: $this->booking->id) . " has been approved.")
            ->line("**Approved Amount:** {$amountStr}")
            ->line("The refund is now being processed. You should see the funds reflected in your account within 3-5 business days depending on your bank/provider.")
            ->action('View Details', config('app.frontend_url') . '/dashboard/bookings');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lab_booking_refund_approved',
            'title' => 'Refund Approved',
            'message' => "Your refund of " . number_format($this->refund->amount, 2) . " has been approved and is being processed.",
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
