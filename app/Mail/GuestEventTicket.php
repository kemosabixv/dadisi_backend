<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuestEventTicket extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public $record
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $event = $this->record->event;
        return new Envelope(
            subject: "Your Ticket for {$event->title}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $qrCodeData = null;
        if ($this->record->qr_code_media_id) {
            try {
                $media = $this->record->qrCodeMedia()->with('file')->first();
                if ($media && $media->file) {
                    $qrCodeData = \Illuminate\Support\Facades\Storage::disk($media->file->disk)->get($media->file->path);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to fetch QR code from CAS for guest ticket', [
                    'record_id' => $this->record->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return new Content(
            markdown: 'emails.events.guest-ticket',
            with: [
                'event' => $this->record->event,
                'record' => $this->record,
                'name' => $this->record->guest_name ?? 'Guest',
                'qrCodeData' => $qrCodeData,
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
