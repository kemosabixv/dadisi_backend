<?php

namespace App\Notifications;

use App\Models\EventOrder;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketPurchaseConfirmation extends Notification
{
    public function __construct(
        protected EventOrder $order
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', \App\Channels\SupabaseChannel::class];
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
        $event = $this->order->event;
        $qrCodeData = null;

        if ($this->order->qr_code_media_id) {
            try {
                $media = $this->order->qrCodeMedia()->with('file')->first();
                if ($media && $media->file) {
                    $qrCodeData = \Illuminate\Support\Facades\Storage::disk($media->file->disk)->get($media->file->path);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to fetch QR code from CAS for ticket purchase notification', [
                    'order_id' => $this->order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return (new MailMessage)
            ->subject("Ticket Confirmed: {$event->title}")
            ->markdown('emails.events.ticket-confirmed', [
                'order' => $this->order,
                'event' => $event,
                'name' => $this->order->attendee_name,
                'qrCodeData' => $qrCodeData,
            ]);
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
            'total' => (float) $this->order->total_amount,
            'currency' => $this->order->currency,
            'link' => "/events/tickets/{$this->order->qr_code_token}",
        ];
    }
}
