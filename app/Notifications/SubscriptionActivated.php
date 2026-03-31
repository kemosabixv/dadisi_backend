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
        return ['mail', 'database', \App\Channels\SupabaseChannel::class, \NotificationChannels\OneSignal\OneSignalChannel::class];
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

        return \NotificationChannels\OneSignal\OneSignalMessage::create()
            ->setSubject('Subscription Activated!')
            ->setBody("Your {$planName} subscription is now active. Enjoy your benefits!")
            ->setUrl(config('app.frontend_url') . '/dashboard/subscription')
            ->setData('type', 'subscription_activated')
            ->setData('subscription_id', $this->subscription->id);
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
        $plan = $this->subscription->plan;
        $planName = $plan?->display_name ?? 'Premium';

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
        $planName = $plan?->display_name ?? 'Premium';

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
