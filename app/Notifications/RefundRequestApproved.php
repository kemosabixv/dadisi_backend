<?php

namespace App\Notifications;

use App\Models\Refund;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Channels\SupabaseChannel;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notification sent to users/guests when a refund request is approved.
 */
class RefundRequestApproved extends Notification
{
    public function __construct(
        protected Refund $refund
    ) {}

    public function via(object $notifiable): array
    {
        // For guests, notifiable is an AnonymousNotifiable, so we only use mail.
        // For users, we use all channels.
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return ['mail'];
        }

        return ['database', 'mail', SupabaseChannel::class, WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title('Refund Request Approved')
            ->icon('/logo.png')
            ->body("Good news! Your refund request for {$this->refund->currency} " . number_format($this->refund->amount, 2) . " has been approved.")
            ->action('View Dashboard', 'view_dashboard');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amountStr = "{$this->refund->currency} " . number_format($this->refund->amount, 2);
        
        $refundable = $this->refund->refundable;
        $trackingUrl = $this->refund->tracking_url;
        
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            $trackingUrl = $this->refund->getGuestTrackingUrl();
        }

        return (new MailMessage)
            ->subject('Refund Request Approved')
            ->markdown('emails.refunds.request-approved', [
                'refund' => $this->refund,
                'amountStr' => $amountStr,
                'user' => $notifiable,
                'trackingUrl' => $trackingUrl,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'refund_request_approved',
            'title' => 'Refund Approved',
            'message' => "Your refund request for {$this->refund->currency} " . number_format($this->refund->amount, 2) . " has been approved.",
            'refund_id' => $this->refund->id,
            'amount' => (float) $this->refund->amount,
            'currency' => $this->refund->currency,
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
