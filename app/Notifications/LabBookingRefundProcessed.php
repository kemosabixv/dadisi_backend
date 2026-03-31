<?php

namespace App\Notifications;

use App\Channels\SupabaseChannel;
use App\Models\LabBooking;
use App\Models\Refund;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to users when their lab booking refund is successfully processed.
 */
class LabBookingRefundProcessed extends Notification
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
            ->setSubject('Refund Processed')
            ->setBody("Great news! Your refund for lab booking #" . ($this->booking->booking_reference ?: $this->booking->id) . " has been successfully processed.")
            ->setUrl(config('app.frontend_url') . "/dashboard")
            ->setData('type', 'lab_booking_refund_processed')
            ->setData('booking_id', $this->booking->id);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amountStr = "{$this->refund->currency} " . number_format($this->refund->amount, 2);
        
        return (new MailMessage)
            ->subject('Refund Completed: Lab Booking')
            ->greeting('Hello ' . $this->booking->payer_name . ',')
            ->line("Great news! Your refund for lab space booking " . ($this->booking->booking_reference ?: $this->booking->id) . " has been successfully processed.")
            ->line("**Refunded Amount:** {$amountStr}")
            ->line("The funds should now be available in your original payment method.")
            ->action('View My Dashboard', config('app.frontend_url') . '/dashboard');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lab_booking_refund_processed',
            'title' => 'Refund Completed',
            'message' => "Your refund of " . number_format($this->refund->amount, 2) . " has been successfully processed.",
            'booking_id' => $this->booking->id,
            'refund_id' => $this->refund->id,
            'link' => '/dashboard',
        ];
    }

    public function toSupabase(object $notifiable): array
    {
        $data = $this->toArray($notifiable);
        $data['recipient_type'] = 'user';
        return $data;
    }
}
