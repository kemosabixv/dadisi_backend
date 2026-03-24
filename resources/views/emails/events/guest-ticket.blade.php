@component('mail::message')
# Hello {{ $name }},

Your ticket for **{{ $event->title }}** has been confirmed!

**Event Details:**
* **Date:** {{ $event->starts_at->format('F j, Y') }}
* **Time:** {{ $event->starts_at->format('g:i A') }}
@if($event->venue)
* **Venue:** {{ $event->venue }}
@endif
@if($record->quantity > 1)
* **Quantity:** {{ $record->quantity }}
@endif

Show your QR code at the venue for check-in. You can access your digital ticket and extra event information by clicking the button below. Please have your QR code ready at the venue for check-in.

@component('mail::button', ['url' => config('app.frontend_url') . '/events/tickets/' . $record->qr_code_token])
View Ticket & QR Code
@endcomponent

@if($qrCodeData)
<div style="text-align: center; margin-top: 20px;">
<img src="{{ $message->embedData($qrCodeData, 'qrcode.svg', 'image/svg+xml') }}" alt="QR Code" width="200" style="display: block; margin: 0 auto;">
<p style="font-size: 12px; color: #666;">QR Token: {{ $record->qr_code_token }}</p>
</div>
@endif

Thank you for joining us!

Regards,
{{ config('app.name') }}
@endcomponent
