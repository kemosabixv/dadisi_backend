<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionActivated extends Notification
{

    public function __construct(
        protected $subscription
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', \App\Channels\SupabaseChannel::class, \NotificationChannels\WebPush\WebPushChannel::class];
    }

    /**
     * Get the WebPush representation of the notification.
     */
    public function toWebPush($notifiable, $notification)
    {
        return (new \NotificationChannels\WebPush\WebPushMessage)
            ->title('Subscription Activated')
            ->icon('/logo.png')
            ->body('Your subscription has been activated successfully! Enjoy your premium benefits.')
            ->action('View Subscription', 'view_subscription');
    }

    /**
     * Get the Supabase representation of the notification.
     */
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
        
        $mail = (new MailMessage)
            ->subject('Subscription Activated!')
            ->greeting("Hello, {$displayName}!")
            ->line("Your {$planName} subscription has been activated.")
            ->line('You now have access to all the features included in your plan:')
            ->line('• Priority event access')
            ->line('• Member-only discounts')
            ->line('• Exclusive content')
            ->line('• Lab space bookings')
            ->action('View Your Subscription', config('app.frontend_url') . '/dashboard/subscription');

        // Try to find the latest payment for this subscription to provide a receipt link
        $payment = $this->subscription->payments()->where('status', 'paid')->latest()->first();
        if ($payment && $payment->reference) {
            $mail->line('You can view your payment receipt by clicking the link below:')
                 ->action('View Payment Receipt', config('app.frontend_url') . '/dashboard/subscription/receipt/' . $payment->reference);
        }

        return $mail->line('Thank you for supporting Dadisi Community Labs!');
    }

    public function toArray(object $notifiable): array
    {
        $plan = $this->subscription->plan;
        $planName = $plan?->name ?? 'Premium';
        
        return [
            'type' => 'subscription_activated',
            'title' => 'Subscription Activated',
            'message' => "Your {$planName} subscription is now active!",
            'subscription_id' => $this->subscription->id,
            'plan_id' => $plan?->id,
            'plan_name' => $planName,
            'link' => '/dashboard/subscription',
        ];
    }
}
