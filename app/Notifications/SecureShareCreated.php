<?php

namespace App\Notifications;

use App\Models\Media;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecureShareCreated extends Notification
{

    private $media;

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
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'share_created',
            'media_id' => $this->media->id,
            'file_name' => $this->media->file_name,
            'share_token' => $this->media->share_token,
            'message' => 'A secure share link was created for "' . $this->media->file_name . '".',
        ];
    }
}
