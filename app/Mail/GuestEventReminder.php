<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuestEventReminder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public $event,
        public $type,
        public $record
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $typeName = $this->type === '24h' ? 'Starts in 24 Hours' : 'Starting Soon';
        return new Envelope(
            subject: "[Reminder] {$this->event->title} - {$typeName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.events.guest-reminder',
            with: [
                'event' => $this->event,
                'type' => $this->type,
                'record' => $this->record,
                'name' => $this->record->guest_name ?? 'Guest',
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
