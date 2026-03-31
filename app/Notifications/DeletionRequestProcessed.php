<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Channels\SupabaseChannel;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

class DeletionRequestProcessed extends Notification implements ShouldQueue
{
    use Queueable;

    private $type;
    private $name;
    private $status;
    private $comment;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $type, string $name, string $status, ?string $comment = null)
    {
        $this->type = $type;
        $this->name = $name;
        $this->status = $status;
        $this->comment = $comment;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', SupabaseChannel::class, OneSignalChannel::class];
    }

    /**
     * Get the OneSignal representation of the notification.
     *
     * @param mixed $notifiable
     * @return \NotificationChannels\OneSignal\OneSignalMessage
     */
    public function toOneSignal($notifiable)
    {
        $statusStr = ucfirst($this->status);
        $body = "Your request to delete the {$this->type} '{$this->name}' has been {$this->status}.";
        
        return OneSignalMessage::create()
            ->setSubject("Deletion Request {$statusStr}")
            ->setBody($body)
            ->setUrl(config('app.frontend_url') . ($this->status === 'rejected' ? "/dashboard" : "/"))
            ->setData('type', 'deletion_request_processed')
            ->setData('resource_type', $this->type)
            ->setData('status', $this->status);
    }

    public function toSupabase(object $notifiable): array
    {
        $data = $this->toArray($notifiable);
        $data['recipient_type'] = 'user';
        return $data;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Deletion Request ' . ucfirst($this->status),
            'message' => "Your request to delete the {$this->type} '{$this->name}' has been " . $this->status . ".",
            'type' => $this->type,
            'name' => $this->name,
            'status' => $this->status,
            'comment' => $this->comment,
            'action_url' => $this->status === 'rejected' ? "/user/blog/{$this->type}s" : null,
        ];
    }
}
