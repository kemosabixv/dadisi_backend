<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
