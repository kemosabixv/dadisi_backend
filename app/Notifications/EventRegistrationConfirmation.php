<?php

namespace App\Notifications;

use App\Models\EventRegistration;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventRegistrationConfirmation extends Notification
{

    public function __construct(
        protected EventRegistration $registration
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->registration->event;
        
        return (new MailMessage)
            ->subject("RSVP Confirmed: {$event->title}")
            ->greeting("Hello {$this->registration->user->name}!")
            ->line("Your RSVP for **{$event->title}** has been confirmed.")
            ->line("**Date:** " . $event->starts_at->format('F j, Y \a\t g:i A'))
            ->line("**Ticket Type:** {$this->registration->ticket->name}")
            ->line("**Confirmation Code:** {$this->registration->confirmation_code}")
            ->action('View Your Ticket', url("/dashboard/events"))
            ->line('Show your QR code at the venue for check-in.')
            ->line('We look forward to seeing you there!');
    }

    public function toArray(object $notifiable): array
    {
        $event = $this->registration->event;
        
        return [
            'type' => 'event_registration',
            'title' => 'RSVP Confirmed',
            'message' => "Your RSVP for {$event->title} has been confirmed.",
            'registration_id' => $this->registration->id,
            'event_id' => $event->id,
            'event_title' => $event->title,
            'event_date' => $event->starts_at->toISOString(),
            'link' => "/dashboard/events",
        ];
    }
}
