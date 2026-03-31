<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Channels\SupabaseChannel;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

class SubscriptionPaymentFailed extends Notification
{
    public function __construct(
        protected $subscription,
        protected $error = null
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
        $planName = $this->subscription->plan?->display_name ?? 'Premium';
        
        return OneSignalMessage::create()
            ->setSubject('Payment Failed')
            ->setBody("We were unable to process the payment for your {$planName} subscription. Please update your details.")
            ->setUrl(config('app.frontend_url') . "/dashboard/subscription")
            ->setData('type', 'subscription_payment_failed')
            ->setData('subscription_id', $this->subscription->id);
    }

    public function toSupabase(object $notifiable): array
    {
        $data = $this->toArray($notifiable);
        $data['recipient_type'] = 'user';
        return $data;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $planName = $this->subscription->plan?->name ?? 'Premium';
        $displayName = $notifiable->display_name ?? $notifiable->username ?? 'Valued Member';
        
        return (new MailMessage)
            ->error()
            ->subject('Action Required: Subscription Payment Failed')
            ->greeting("Hello, {$displayName}!")
            ->line("We were unable to process the payment for your {$planName} subscription.")
            ->line('Cause: ' . ($this->error ?: 'Transaction failed at the gateway.'))
            ->line('To maintain your premium benefits and avoid service interruption, please update your payment method or retry the payment.')
            ->action('Update Payment Method', config('app.frontend_url') . '/dashboard/subscription')
            ->line('If you need assistance, please contact our support team.');
    }

    public function toArray(object $notifiable): array
    {
        $plan = $this->subscription->plan;
        
        return [
            'type' => 'subscription_payment_failed',
            'title' => 'Payment Failed',
            'message' => "We couldn't process the payment for your {$plan?->display_name} subscription.",
            'subscription_id' => $this->subscription->id,
            'plan_id' => $plan?->id,
            'error' => $this->error,
            'link' => '/dashboard/subscription',
        ];
    }
}
