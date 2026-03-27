<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\EventRegistration;
use App\Models\EventOrder;

class EventWaitlistPromoted extends Notification implements ShouldQueue
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
        
        $mail = (new MailMessage)
            ->subject("Waitlist Promotion: {$event->title}")
            ->line("Good news! A spot has become available for **{$event->title}**.")
            ->line("You have been promoted from the waitlist.");

        $frontendUrl = config('app.frontend_url', url('/'));
        $actionUrl = $frontendUrl . "/dashboard/events";

        if (!$this->model->user_id && $this->model->qr_code_token) {
            $actionUrl = $frontendUrl . "/events/tickets/" . $this->model->qr_code_token;
        }

        if ($this->model instanceof EventOrder) {
            $mail->line("Please complete your payment to finalize your registration.")
                 ->action('Complete Payment', $actionUrl);
        } else {
            $mail->line("Your registration is now confirmed.")
                 ->action('View My Ticket', $actionUrl);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        $event = $this->model->event;
        
        return [
            'type' => 'event_waitlist_promoted',
            'title' => 'Waitlist Promotion',
            'message' => "You have been promoted from the waitlist for {$event->title}!",
            'event_id' => $event->id,
            'event_title' => $event->title,
            'needs_payment' => $this->model instanceof EventOrder,
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
