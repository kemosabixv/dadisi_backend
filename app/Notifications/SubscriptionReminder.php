<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

class SubscriptionReminder extends Notification
{
    public function __construct(
        protected $subscription,
        protected $daysRemaining
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
        return \NotificationChannels\OneSignal\OneSignalMessage::create()
            ->setSubject('Subscription Renewal Reminder')
            ->setBody("Your subscription expires in {$this->daysRemaining} days. Renew now to keep your benefits.")
            ->setUrl(config('app.frontend_url') . '/dashboard/subscription')
            ->setData('type', 'subscription_reminder')
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
        $formattedDate = Carbon::parse($endsAt)->format('F j, Y');
        
        return (new MailMessage)
            ->subject('Upcoming Subscription Renewal')
            ->greeting("Hello, {$displayName}!")
            ->line("Your {$planName} subscription is scheduled to expire in {$this->daysRemaining} days, on {$formattedDate}.")
            ->line('Renew now to ensure uninterrupted access to priority event registration, lab space bookings, and exclusive content.')
            ->action('Renew Subscription', config('app.frontend_url') . '/dashboard/subscription')
            ->line('Thank you for being a valued member of Dadisi Community Labs!');
    }

    public function toArray(object $notifiable): array
    {
        $plan = $this->subscription->plan;
        
        return [
            'type' => 'subscription_reminder',
            'title' => 'Renewal Reminder',
            'message' => "Your {$plan?->display_name} subscription expires in {$this->daysRemaining} days.",
            'subscription_id' => $this->subscription->id,
            'plan_id' => $plan?->id,
            'days_remaining' => $this->daysRemaining,
            'ends_at' => $this->subscription->ends_at,
            'link' => '/dashboard/subscription',
        ];
    }
}
