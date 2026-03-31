<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

class StorageUsageAlert extends Notification
{

    public $percentage;
    public $limitMB;

    /**
     * Create a new notification instance.
     */
    public function __construct(int $percentage, int $limitMB)
    {
        $this->percentage = $percentage;
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
            ->setSubject('Storage Usage Alert')
            ->setBody("Your storage usage has reached {$this->percentage}% of your {$this->limitMB}MB limit.")
            ->setUrl(config('app.frontend_url') . '/dashboard/media')
            ->setData('type', 'storage_alert');
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
                    ->subject('Storage Usage Alert - ' . $this->percentage . '%')
                    ->line('Your cloud storage usage has reached ' . $this->percentage . '% of your ' . $this->limitMB . 'MB limit.')
                    ->action('Manage Media', url('/dashboard/media'))
                    ->line('Please consider deleting old files to free up space.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'storage_alert',
            'percentage' => $this->percentage,
            'limit_mb' => $this->limitMB,
            'message' => 'You have used ' . $this->percentage . '% of your storage quota.',
        ];
    }
}
