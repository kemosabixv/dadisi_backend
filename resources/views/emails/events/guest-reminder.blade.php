@component('mail::message')
# Hello {{ $name }},

This is a reminder for **{{ $event->title }}**.

@if($type === '24h')
The event will start in **24 hours**.
@else
The event is starting in **1 hour**!
@endif

**Event Details:**
* **Date:** {{ $event->starts_at->format('F j, Y') }}
* **Time:** {{ $event->starts_at->format('g:i A') }}
@if($event->venue)
* **Venue:** {{ $event->venue }}
@endif

Please have your QR code ready for check-in.

@component('mail::button', ['url' => config('app.frontend_url') . '/events/tickets/' . $record->qr_code_token])
View Ticket & QR Code
@endcomponent

We look forward to seeing you!

Regards,
{{ config('app.name') }}
@endcomponent
