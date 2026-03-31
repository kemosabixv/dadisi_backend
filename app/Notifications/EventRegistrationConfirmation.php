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
        return ['mail', 'database', \App\Channels\SupabaseChannel::class, \NotificationChannels\OneSignal\OneSignalChannel::class];
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
        
        return \NotificationChannels\OneSignal\OneSignalMessage::create()
            ->setSubject('RSVP Confirmed')
            ->setBody("You're registered for {$event->title}!")
            ->setUrl(config('app.frontend_url') . "/dashboard/events")
            ->setData('type', 'event_registration')
            ->setData('registration_id', $this->registration->id);
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
        $qrCodeData = null;

        if ($this->registration->qr_code_media_id) {
            try {
                $media = $this->registration->qrCodeMedia()->with('file')->first();
                if ($media && $media->file) {
                    $qrCodeData = \Illuminate\Support\Facades\Storage::disk($media->file->disk)->get($media->file->path);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to fetch QR code from CAS for event registration notification', [
                    'registration_id' => $this->registration->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return (new MailMessage)
            ->subject("RSVP Confirmed: {$event->title}")
            ->markdown('emails.events.registration-confirmed', [
                'registration' => $this->registration,
                'event' => $event,
                'user' => $notifiable,
                'qrCodeData' => $qrCodeData,
            ]);
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
