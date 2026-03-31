<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;
use App\Channels\SupabaseChannel;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

class SubscriptionCancelled extends Notification
{
    public function __construct(
        protected $subscription,
        protected $reason = null
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
            ->setSubject('Subscription Cancelled')
            ->setBody("Your {$planName} subscription has been cancelled. You will retain access until the end of your billing period.")
            ->setUrl(config('app.frontend_url') . "/dashboard/subscription")
            ->setData('type', 'subscription_cancelled')
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
        $endsAt = $this->subscription->ends_at;
        
        $message = (new MailMessage)
            ->subject('Subscription Cancellation Confirmation')
            ->greeting("Hello, {$displayName}!")
            ->line("This is to confirm that your {$planName} subscription has been cancelled.");

        if ($endsAt) {
            $formattedDate = Carbon::parse($endsAt)->format('F j, Y');
            $message->line("You will continue to have access to all premium features until {$formattedDate}.");
        }

        return $message
            ->line('We are sorry to see you go! If you change your mind, you can reactivate your subscription anytime before it expires.')
            ->action('Manage Subscription', config('app.frontend_url') . '/dashboard/subscription')
            ->line('Thank you for being part of Dadisi Community Labs.');
    }

    public function toArray(object $notifiable): array
    {
        $plan = $this->subscription->plan;
        
        return [
            'type' => 'subscription_cancelled',
            'title' => 'Subscription Cancelled',
            'message' => "Your {$plan?->display_name} subscription has been cancelled.",
            'subscription_id' => $this->subscription->id,
            'plan_id' => $plan?->id,
            'ends_at' => $this->subscription->ends_at,
            'link' => '/dashboard/subscription',
        ];
    }
}
