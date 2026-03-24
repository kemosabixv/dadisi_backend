<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionPaymentFailed extends Notification
{
    public function __construct(
        protected $subscription,
        protected $error = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', \App\Channels\SupabaseChannel::class, \NotificationChannels\WebPush\WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification)
    {
        return (new \NotificationChannels\WebPush\WebPushMessage)
            ->title('Subscription Payment Failed')
            ->icon('/logo.png')
            ->body('We were unable to process your subscription payment. Please check your payment details.')
            ->action('Retry Payment', 'retry_payment');
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
            'message' => "We couldn't process the payment for your {$plan?->name} subscription.",
            'subscription_id' => $this->subscription->id,
            'plan_id' => $plan?->id,
            'error' => $this->error,
            'link' => '/dashboard/subscription',
        ];
    }
}
