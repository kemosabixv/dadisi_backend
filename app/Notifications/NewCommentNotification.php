<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewCommentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $comment;
    protected $post;

    /**
     * Create a new notification instance.
     */
    public function __construct(Comment $comment, Post $post)
    {
        $this->comment = $comment;
        $this->post = $post;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = config('app.frontend_url') . '/blog/' . $this->post->slug;

        return (new MailMessage)
            ->subject('New Comment on your Post: ' . $this->post->title)
            ->greeting('Hello ' . $notifiable->username . ',')
            ->line($this->comment->user->username . ' left a comment on your post "' . $this->post->title . '".')
            ->line('"' . substr($this->comment->body, 0, 100) . (strlen($this->comment->body) > 100 ? '...' : '') . '"')
            ->action('View Comment', $url)
            ->line('Thank you for being part of our community!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_comment',
            'post_id' => $this->post->id,
            'post_title' => $this->post->title,
            'post_slug' => $this->post->slug,
            'comment_id' => $this->comment->id,
            'commenter_id' => $this->comment->user_id,
            'commenter_name' => $this->comment->user->username,
            'message' => $this->comment->user->username . ' commented on your post.',
        ];
    }
}
