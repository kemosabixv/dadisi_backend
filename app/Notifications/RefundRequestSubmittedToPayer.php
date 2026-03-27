<?php

namespace App\Notifications;

use App\Models\Refund;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Channels\SupabaseChannel;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class RefundRequestSubmittedToPayer extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Refund $refund
    ) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return ['mail'];
        }

        return ['database', 'mail', SupabaseChannel::class, WebPushChannel::class];
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
            ->subject('Refund Request Received')
            ->markdown('emails.refunds.request-submitted', [
                'refund' => $this->refund,
                'amountStr' => $amountStr,
                'user' => $notifiable,
                'trackingUrl' => $trackingUrl,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'refund_request_submitted_payer',
            'title' => 'Refund Request Received',
            'message' => "We've received your refund request for {$this->refund->currency} " . number_format($this->refund->amount, 2) . ".",
            'refund_id' => $this->refund->id,
            'amount' => (float) $this->refund->amount,
            'currency' => $this->refund->currency,
            'link' => $this->refund->tracking_url,
        ];
    }

    public function toSupabase(object $notifiable): array
    {
        $data = $this->toArray($notifiable);
        $data['recipient_type'] = 'user';
        return $data;
    }

    public function toWebPush($notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title('Refund Request Received')
            ->icon('/logo.png')
            ->body("We've received your refund request for {$this->refund->currency} " . number_format($this->refund->amount, 2) . ".")
            ->action('View Dashboard', 'view_dashboard');
    }
}
