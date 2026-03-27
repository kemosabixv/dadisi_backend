@component('mail::message')
# Your Refund is Complete

Hello {{ $user->username ?? $user->name ?? 'there' }},

Your refund of **{{ $amountStr }}** has been successfully processed.

The funds should appear in your original payment method within 5-10 business days depending on your bank.

**Refund Details:**
- **Amount:** {{ $amountStr }}
- **Reference:** #{{ $refund->id }}

Thank you for your patience and for being part of Dadisi Community Labs.

@component('mail::button', ['url' => $trackingUrl])
View Status
@endcomponent

Regards,<br>
{{ config('app.name') }}
@endcomponent
