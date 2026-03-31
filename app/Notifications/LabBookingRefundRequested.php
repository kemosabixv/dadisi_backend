<?php

namespace App\Notifications;

use App\Models\LabBooking;
use App\Models\Refund;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Channels\SupabaseChannel;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

/**
 * Sent to staff when a new lab booking refund is requested.
 */
class LabBookingRefundRequested extends Notification
{
    public function __construct(
        protected LabBooking $booking,
        protected Refund $refund
    ) {}

    public function via(object $notifiable): array
    {
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
            ->setSubject('New Lab Refund Request')
            ->setBody("{$this->booking->payer_name} requested a refund for booking #" . ($this->booking->booking_reference ?: $this->booking->id))
            ->setUrl(config('app.frontend_url') . "/admin/refunds/" . $this->refund->id)
            ->setData('type', 'staff_lab_refund_requested')
            ->setData('refund_id', $this->refund->id);
    }

    public function toSupabase(object $notifiable): array
    {
        $data = $this->toArray($notifiable);
        $data['recipient_type'] = 'admin';
        return $data;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amountStr = "{$this->refund->currency} " . number_format($this->refund->amount, 2);
        
        return (new MailMessage)
            ->subject('Urgent: Lab Booking Refund Request')
            ->line("A new refund request has been submitted for a lab booking.")
            ->line("**Payer:** " . $this->booking->payer_name)
            ->line("**Amount:** {$amountStr}")
            ->line("**Booking Ref:** " . ($this->booking->booking_reference ?: $this->booking->id))
            ->line("**Reason:** " . $this->refund->reason)
            ->action('Review in Admin Panel', url('/admin/refunds/' . $this->refund->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'staff_lab_refund_requested',
            'title' => 'New Lab Refund Request',
            'message' => "{$this->booking->payer_name} requested a refund of " . number_format($this->refund->amount, 2) . " for booking " . ($this->booking->booking_reference ?: $this->booking->id),
            'refund_id' => $this->refund->id,
            'link' => '/admin/refunds/' . $this->refund->id,
        ];
    }
}
