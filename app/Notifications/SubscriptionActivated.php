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
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $planName = $this->subscription->plan?->name ?? 'Premium';
        
        return (new MailMessage)
            ->subject('Subscription Activated!')
            ->greeting("Welcome, {$notifiable->name}!")
            ->line("Your {$planName} subscription has been activated.")
            ->line('You now have access to all the features included in your plan:')
            ->line('• Priority event access')
            ->line('• Member-only discounts')
            ->line('• Exclusive content')
            ->line('• Lab space bookings')
            ->action('View Your Subscription', url('/dashboard/subscription'))
            ->line('Thank you for supporting Dadisi Community Labs!');
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
