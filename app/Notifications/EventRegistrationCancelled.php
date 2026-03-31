<?php

namespace App\Notifications;

use App\Models\EventRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Channels\SupabaseChannel;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

class EventRegistrationCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected EventRegistration $registration,
        protected ?string $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return ['mail'];
        }
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
        $event = $this->registration->event;
        
        return OneSignalMessage::create()
            ->setSubject('RSVP Cancelled')
            ->setBody("Your RSVP for {$event->title} has been cancelled.")
            ->setUrl(config('app.frontend_url') . "/events")
            ->setData('type', 'event_registration_cancelled')
            ->setData('event_id', $event->id);
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

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->registration->event;
        
        return (new MailMessage)
            ->subject("RSVP Cancelled: {$event->title}")
            ->markdown('emails.events.registration-cancelled', [
                'registration' => $this->registration,
                'event' => $event,
                'user' => $notifiable,
                'reason' => $this->reason,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        $event = $this->registration->event;
        
        return [
            'type' => 'event_registration_cancelled',
            'title' => 'RSVP Cancelled',
            'message' => "Your RSVP for {$event->title} has been cancelled.",
            'registration_id' => $this->registration->id,
            'event_id' => $event->id,
            'event_title' => $event->title,
            'event_date' => $event->starts_at->toISOString(),
            'link' => "/events",
        ];
    }
}
