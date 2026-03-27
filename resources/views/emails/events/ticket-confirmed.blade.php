@component('mail::message')
# Hello {{ $name }},

Your ticket purchase for **{{ $event->title }}** has been confirmed!

**Order Details:**
* **Event:** {{ $event->title }}
* **Date:** {{ $event->starts_at->format('F j, Y') }}
* **Time:** {{ $event->starts_at->format('g:i A') }}
@if($event->venue)
* **Venue:** {{ $event->venue }}
@endif
* **Quantity:** {{ $order->quantity }}
* **Total Amount:** {{ $order->currency }} {{ number_format($order->total_amount, 2) }}
* **Order Reference:** {{ $order->reference }}

You can access your digital ticket and extra event information by clicking the button below. Please have your QR code ready at the venue for check-in.

@component('mail::button', ['url' => config('app.frontend_url') . '/events/tickets/' . $order->qr_code_token])
View Your Ticket
@endcomponent

@if($qrCodeData)
<div style="text-align: center; margin-top: 20px;">
<img src="{{ $message->embedData($qrCodeData, 'qrcode.svg', 'image/svg+xml') }}" alt="QR Code" width="200" style="display: block; margin: 0 auto;">
<p style="font-size: 12px; color: #666;">QR Token: {{ $order->qr_code_token }}</p>
</div>
@endif

Thank you for your purchase!

Regards,<br>
{{ config('app.name') }}
@endcomponent
