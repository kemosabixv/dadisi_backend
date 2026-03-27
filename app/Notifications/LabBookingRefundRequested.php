<?php

namespace App\Notifications;

use App\Models\LabBooking;
use App\Models\Refund;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
        return ['mail', 'database'];
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
