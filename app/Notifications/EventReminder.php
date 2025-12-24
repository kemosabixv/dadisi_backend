<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Event $event,
        protected string $reminderType = '24h' // '24h', '1h', or 'starting'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $timeText = match ($this->reminderType) {
            '24h' => 'tomorrow',
            '1h' => 'in 1 hour',
            'starting' => 'starting now',
            default => 'soon',
        };

        return (new MailMessage)
            ->subject("Reminder: {$this->event->title} is {$timeText}!")
            ->greeting("Hello!")
            ->line("This is a reminder that {$this->event->title} is {$timeText}.")
            ->line("**Date:** " . $this->event->starts_at->format('F j, Y \a\t g:i A'))
            ->when($this->event->venue, fn($m) => $m->line("**Venue:** {$this->event->venue}"))
            ->when($this->event->is_online, fn($m) => $m->line("**Format:** Online Event"))
            ->action('View Event Details', url("/events/{$this->event->slug}"))
            ->line('We look forward to seeing you there!');
    }

    public function toArray(object $notifiable): array
    {
        $timeText = match ($this->reminderType) {
            '24h' => 'tomorrow',
            '1h' => 'in 1 hour',
            'starting' => 'now',
            default => 'soon',
        };

        return [
            'type' => 'event_reminder',
            'title' => 'Event Reminder',
            'message' => "{$this->event->title} is {$timeText}!",
            'event_id' => $this->event->id,
            'event_slug' => $this->event->slug,
            'event_title' => $this->event->title,
            'event_date' => $this->event->starts_at->toISOString(),
            'reminder_type' => $this->reminderType,
            'link' => "/events/{$this->event->slug}",
        ];
    }
}
