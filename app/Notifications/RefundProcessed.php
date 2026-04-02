<?php

namespace App\Notifications;

use App\Channels\SupabaseChannel;
use App\Models\Refund;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

class RefundProcessed extends Notification
{

    public function __construct(
        protected Refund $refund
    ) {}

    public function via($notifiable)
    {
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return ['mail'];
        }

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
        $amount = number_format($this->refund->amount, 2);
        
        return OneSignalMessage::create()
            ->setSubject('Refund Processed')
            ->setBody("Your refund of {$this->refund->currency} {$amount} for {$this->refund->refundable_title} has been processed.")
            ->setUrl(config('app.frontend_url') . '/dashboard')
            ->setData('type', 'refund_processed')
            ->setData('refund_id', $this->refund->id);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->refund->refundable_title;

        return (new MailMessage)
            ->subject('Refund Processed: '.$title)
            ->greeting('Hello!')
            ->line('Your refund request has been processed and completed.')
            ->line('Amount: '.$this->refund->currency.' '.number_format($this->refund->amount, 2))
            ->line('Reason: '.($this->refund->reason_display ?? $this->refund->reason))
            ->line('The funds should appear in your account within 3-5 business days depending on your payment method.')
            ->action('View My Dashboard', $this->refund->tracking_url)
            ->line('Thank you for your patience.');
    }

    public function toArray(object $notifiable): array
    {
        $title = $this->refund->refundable_title;

        return [
            'type' => 'refund_processed',
            'title' => 'Refund Processed',
            'message' => "Your refund of {$this->refund->currency} ".number_format($this->refund->amount, 2)." for {$title} has been processed.",
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
}
