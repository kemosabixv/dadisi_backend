<?php

namespace App\Notifications;

use App\Models\Refund;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Channels\SupabaseChannel;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

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

        return ['database', 'mail', SupabaseChannel::class, OneSignalChannel::class];
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
            ->setSubject('Refund Request Rejected')
            ->setBody("Your refund request has been rejected. Please check your dashboard for details.")
            ->setUrl(config('app.frontend_url') . '/dashboard')
            ->setData('type', 'refund_request_rejected')
            ->setData('refund_id', $this->refund->id);
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
