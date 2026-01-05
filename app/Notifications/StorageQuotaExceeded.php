<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StorageQuotaExceeded extends Notification
{

    private $limitMB;

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
        return ['mail', 'database'];
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
