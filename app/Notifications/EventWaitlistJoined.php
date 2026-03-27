<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\EventRegistration;
use App\Models\EventOrder;

class EventWaitlistJoined extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected EventRegistration|EventOrder $model
    ) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return ['mail'];
        }
        return ['mail', 'database', \App\Channels\SupabaseChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->model->event;
        $position = $this->model->waitlist_position;
        
        // Handle priority naming
        $positionText = $position >= 1000000 ? "Priority Position " . ($position - 999999) : "Waitlist Position {$position}";

        $mail = (new MailMessage)
            ->subject("Waitlist Joined: {$event->title}")
            ->line("The event **{$event->title}** is currently at full capacity.")
            ->line("You have been added to the waitlist.")
            ->line("**{$positionText}**")
            ->line("We will notify you immediately if a spot becomes available.");

        if ($this->model instanceof EventOrder) {
            $mail->line("Since this is a paid event, you will be prompted to complete your payment once you are promoted from the waitlist.");
        }

        $frontendUrl = config('app.frontend_url', url('/'));
        $actionUrl = $frontendUrl . "/dashboard/events";

        if (!$this->model->user_id && $this->model->qr_code_token) {
            $actionUrl = $frontendUrl . "/events/tickets/" . $this->model->qr_code_token;
        }

        return $mail->action('View My Events', $actionUrl);
    }

    public function toArray(object $notifiable): array
    {
        $event = $this->model->event;
        
        return [
            'type' => 'event_waitlist_joined',
            'title' => 'Waitlist Joined',
            'message' => "You have been added to the waitlist for {$event->title}.",
            'event_id' => $event->id,
            'event_title' => $event->title,
            'position' => $this->model->waitlist_position,
            'is_priority' => $this->model->waitlist_position >= 1000000,
            'link' => "/dashboard/events",
        ];
    }

    /**
     * Get the Supabase representation of the notification.
     */
    public function toSupabase(object $notifiable): array
    {
        $data = $this->toArray($notifiable);
        $data['recipient_type'] = 'user';
        return $data;
    }
}
