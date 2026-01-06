<?php

namespace App\Notifications;

use App\Models\Like;
use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewLikeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $like;
    protected $post;

    /**
     * Create a new notification instance.
     */
    public function __construct(Like $like, Post $post)
    {
        $this->like = $like;
        $this->post = $post;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Only send database notification for likes to avoid spamming email
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $action = $this->like->type === 'like' ? 'liked' : 'disliked';

        return [
            'type' => 'new_vote',
            'vote_type' => $this->like->type,
            'post_id' => $this->post->id,
            'post_title' => $this->post->title,
            'post_slug' => $this->post->slug,
            'voter_id' => $this->like->user_id,
            'voter_name' => $this->like->user->username,
            'message' => $this->like->user->username . ' ' . $action . ' your post.',
        ];
    }
}
