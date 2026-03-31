<?php

namespace App\Notifications;

use App\Models\Refund;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Channels\SupabaseChannel;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

/**
 * Notification sent to users/guests when a refund has been successfully completed.
 */
class RefundRequestCompleted extends Notification
{
    public function __construct(
        protected Refund $refund
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
        $amount = number_format($this->refund->amount, 2);

        return OneSignalMessage::create()
            ->setSubject('Refund Completed')
            ->setBody("Your refund of {$this->refund->currency} {$amount} has been successfully processed.")
            ->setUrl(config('app.frontend_url') . '/dashboard')
            ->setData('type', 'refund_request_completed')
            ->setData('refund_id', $this->refund->id);
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
            ->subject('Your Refund is Complete')
            ->markdown('emails.refunds.request-completed', [
                'refund' => $this->refund,
                'amountStr' => $amountStr,
                'user' => $notifiable,
                'trackingUrl' => $trackingUrl,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'refund_request_completed',
            'title' => 'Refund Completed',
            'message' => "Your refund of {$this->refund->currency} " . number_format($this->refund->amount, 2) . " has been successfully processed.",
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
