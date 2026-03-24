<?php

namespace App\Notifications;

use App\Models\Refund;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Channels\SupabaseChannel;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notification sent to users/guests when a refund request is rejected.
 */
class RefundRequestRejected extends Notification
{
    public function __construct(
        protected Refund $refund,
        protected ?string $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return ['mail'];
        }

        return ['database', 'mail', SupabaseChannel::class, WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title('Refund Request Rejected')
            ->icon('/logo.png')
            ->body("Your refund request has been rejected.")
            ->action('View Details', 'view_details');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $refundable = $this->refund->refundable;
        $trackingUrl = $this->refund->tracking_url;
        
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            $trackingUrl = $this->refund->getGuestTrackingUrl();
        }

        return (new MailMessage)
            ->subject('Refund Request Update')
            ->markdown('emails.refunds.request-rejected', [
                'refund' => $this->refund,
                'reason' => $this->reason,
                'user' => $notifiable,
                'trackingUrl' => $trackingUrl,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        $message = $this->reason 
            ? "Your refund request has been rejected. Reason: {$this->reason}" 
            : "Your refund request has been rejected.";

        return [
            'type' => 'refund_request_rejected',
            'title' => 'Refund Request Rejected',
            'message' => $message,
            'refund_id' => $this->refund->id,
            'reason' => $this->reason,
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
