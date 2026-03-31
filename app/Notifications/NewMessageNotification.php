<?php

namespace App\Notifications;

use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

class NewMessageNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public ChatMessage $message)
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', OneSignalChannel::class];
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message_id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $this->message->sender->username,
            'title' => 'New message from ' . $this->message->sender->username,
            'message' => \Illuminate\Support\Str::limit($this->message->content, 50),
            'link' => '/dashboard/chat?conversation=' . $this->message->conversation_id,
            'type' => 'chat_message',
        ];
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
            ->setSubject('New message from ' . $this->message->sender->username)
            ->setBody(\Illuminate\Support\Str::limit($this->message->content, 100))
            ->setUrl(config('app.frontend_url') . '/dashboard/chat?conversation=' . $this->message->conversation_id)
            ->setData('type', 'chat_message')
            ->setData('conversation_id', $this->message->conversation_id);
    }
}
