<?php

namespace App\Notifications;

use App\Models\EventOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketPurchaseConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected EventOrder $order
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->order->event;
        
        return (new MailMessage)
            ->subject("Ticket Confirmed: {$event->title}")
            ->greeting("Hello {$this->order->attendee_name}!")
            ->line("Your ticket purchase has been confirmed.")
            ->line("**Event:** {$event->title}")
            ->line("**Date:** " . $event->starts_at->format('F j, Y \a\t g:i A'))
            ->line("**Tickets:** {$this->order->quantity}")
            ->line("**Total:** {$this->order->currency} " . number_format((float) $this->order->total_amount, 2))
            ->action('View Your Ticket', url("/dashboard/tickets/{$this->order->id}"))
            ->line('Show your QR code at the venue for check-in.')
            ->line('Thank you for your purchase!');
    }

    public function toArray(object $notifiable): array
    {
        $event = $this->order->event;
        
        return [
            'type' => 'ticket_purchase',
            'title' => 'Ticket Purchase Confirmed',
            'message' => "Your ticket for {$event->title} has been confirmed.",
            'order_id' => $this->order->id,
            'event_id' => $event->id,
            'event_title' => $event->title,
            'event_date' => $event->starts_at->toISOString(),
            'quantity' => $this->order->quantity,
            'total' => $this->order->total_amount,
            'currency' => $this->order->currency,
            'link' => "/dashboard/tickets/{$this->order->id}",
        ];
    }
}
