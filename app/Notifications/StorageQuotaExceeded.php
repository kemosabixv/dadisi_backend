<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

class StorageQuotaExceeded extends Notification
{

    public $limitMB;

    /**
     * Create a new notification instance.
     */
    public function __construct(int $limitMB)
    {
        $this->limitMB = $limitMB;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', \App\Channels\SupabaseChannel::class, OneSignalChannel::class];
    }

    /**
     * Get the OneSignal representation of the notification.
     *
     * @param mixed $notifiable
     * @return \NotificationChannels\OneSignal\OneSignalMessage
     */
    public function toOneSignal($notifiable)
    {
        return OneSignalMessage::create()
            ->setSubject('Storage Quota Exceeded')
            ->setBody("You have reached your limit of {$this->limitMB}MB. New uploads are blocked.")
            ->setUrl(config('app.frontend_url') . '/dashboard/media')
            ->setData('type', 'quota_exceeded');
    }

    /**
     * Get the Supabase representation of the notification.
     */
    public function toSupabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->error()
                    ->subject('Storage Quota Exceeded')
                    ->line('You have reached your cloud storage limit of ' . $this->limitMB . 'MB.')
                    ->line('New uploads will be blocked until you free up some space or upgrade your plan.')
                    ->action('View My Storage', url('/dashboard/media'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'quota_exceeded',
            'limit_mb' => $this->limitMB,
            'message' => 'Your storage quota of ' . $this->limitMB . 'MB has been reached.',
        ];
    }
}
