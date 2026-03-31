<?php

namespace App\Notifications;

use App\Models\Media;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Channels\SupabaseChannel;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

class SecureShareCreated extends Notification
{
    private Media $media;

    /**
     * Create a new notification instance.
     */
    public function __construct(Media $media)
    {
        $this->media = $media;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
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
        return OneSignalMessage::create()
            ->setSubject('Secure Share Link Created')
            ->setBody("A secure share link has been created for your file: {$this->media->file_name}")
            ->setUrl(config('app.url') . '/api/media/shared/' . $this->media->share_token)
            ->setData('type', 'media_share_created')
            ->setData('media_id', $this->media->id);
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
        $shareUrl = config('app.url') . '/api/media/shared/' . $this->media->share_token;

        return (new MailMessage)
                    ->subject('Secure Share Link Created')
                    ->line('A secure share link has been created for your file: ' . $this->media->file_name)
                    ->action('View Shared File', $shareUrl)
                    ->line('Anyone with this link can view the file according to the permissions set.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'media_share_created',
            'media_id' => $this->media->id,
            'file_name' => $this->media->file_name,
            'share_token' => $this->media->share_token,
            'message' => 'Secure share link created for ' . $this->media->file_name,
        ];
    }
}
